<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\EntityUser;
use App\Services\PlantContextService;

class SetEntityContext
{
    /**
     * Handle an incoming request.
     *
     * On every authenticated request:
     * 1. Read session('active_entity_id') and session('active_plant_id')
     * 2. Find mm_entity_users row for current user + entity + plant
     * 3. Dynamically bind the Spatie Role for this request lifecycle
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip middleware on entity/plant selection and non-authenticated routes
        if ($request->routeIs('entity-context.*', 'login', 'register', 'password.*', 'verification.*')) {
            return $next($request);
        }

        if (Auth::check()) {
            $user           = Auth::user();

            // Use PlantContextService — resolves session → user default → null.
            // This is the single call that re-hydrates the session if it was lost.
            /** @var PlantContextService $ctx */
            $ctx            = app(PlantContextService::class);
            $activeEntityId = $ctx->entityId();
            $activePlantId  = $ctx->plantId();

            // --- Auto Setup Session if missing ---
            if (!$activeEntityId || !session()->has('active_plant_id')) {
                // 1. Check for user-defined defaults
                if ($user->default_entity_id && $user->default_plant_id) {
                    $activeEntityId = $user->default_entity_id;
                    $activePlantId  = $user->default_plant_id;
                } 
                // 2. Check for "Only One Choice" scenario
                else {
                    $isSuperAdmin = $user->isSystemAdmin();
                    
                    // Count entities
                    if ($isSuperAdmin) {
                        $entities = \App\Models\Entity::all();
                    } else {
                        $entities = EntityUser::where('user_id', $user->id)
                            ->groupBy('entity_id')
                            ->get(['entity_id']);
                    }

                    if ($entities->count() === 1) {
                        $singleEntityId = $isSuperAdmin ? $entities->first()->id : $entities->first()->entity_id;
                        
                        // Count plants for this entity
                        $plantsQuery = \App\Models\Plant::where('entity_id', $singleEntityId)->where('is_active', true);
                        if (!$isSuperAdmin) {
                            $assignments = EntityUser::where('user_id', $user->id)
                                ->where('entity_id', $singleEntityId)
                                ->get(['plant_id']);
                            
                            $hasGlobalAccess = $assignments->contains(fn($a) => is_null($a->plant_id));
                            if (!$hasGlobalAccess) {
                                $plantsQuery->whereIn('id', $assignments->pluck('plant_id'));
                            }
                        }
                        
                        $plants = $plantsQuery->get();
                        if ($plants->count() === 1) {
                            $activeEntityId = $singleEntityId;
                            $activePlantId  = $plants->first()->id;
                        }
                    }
                }

                if ($activeEntityId && $activePlantId) {
                    session([
                        'active_entity_id' => $activeEntityId,
                        'active_plant_id'  => $activePlantId,
                    ]);
                }
            }
            // -------------------------------------

            if ($activeEntityId) {
                // Check if the entity is suspended (except for system admins)
                $entityObj = \App\Models\Entity::find($activeEntityId);
                if ($entityObj && $entityObj->is_suspended != 0 && !$user->isSystemAdmin()) {
                    session()->forget(['active_entity_id', 'active_plant_id']);
                    return redirect()->route('entity-context.index');
                }

                // Check if the active plant is inactive (is_active !== 1) (except for system admins)
                if ($activePlantId) {
                    $plantObj = \App\Models\Plant::find($activePlantId);
                    if ($plantObj && $plantObj->is_active !== 1 && !$user->isSystemAdmin()) {
                        session()->forget('active_plant_id');
                        return redirect()->route('entity-context.index');
                    }
                }

                // Build the query for this user + entity
                $query = EntityUser::with(['entity', 'role', 'plant'])
                    ->where('user_id', $user->id)
                    ->where('entity_id', $activeEntityId);

                // Narrow to plant if one is active
                if ($activePlantId) {
                    $query->where('plant_id', $activePlantId);
                }

                $entityUser = $query->first();

                if ($entityUser && $entityUser->role) {
                    // Dynamically sync the Spatie role — non-persistent, request-scoped only
                    $user->syncRoles([$entityUser->role->name]);
                } else if ($user->isSystemAdmin()) {
                    // Keep System Administrator role
                } else {
                    // No matching row — clear all roles for safety
                    $user->syncRoles([]);
                }
            } else {
                // No entity set — clear all roles (unless system admin)
                if (!$user->isSystemAdmin()) {
                    $user->syncRoles([]);
                }
            }
        }

        // --- FINAL SECURITY CHECK ---
        // If after the above attempts we still don't have an active_plant_id,
        // it means the session is invalid or the user has no access.
        // Redirect them to the selection page instead of logging out.
        if (Auth::check() && !session()->has('active_plant_id')) {
            if ($request->routeIs('entity-context.*')) {
                return $next($request);
            }
            return redirect()->route('entity-context.index');
        }

        // Track last_visit_page for full Inertia page visits (GET requests only)
        if (Auth::check() && $request->header('X-Inertia') && $request->isMethod('GET')) {
            $user = Auth::user();
            $currentUrl = $request->path();
            if ($user->last_visit_page !== $currentUrl) {
                $user->forceFill(['last_visit_page' => $currentUrl])->saveQuietly();
            }
        }

        return $next($request);
    }
}
