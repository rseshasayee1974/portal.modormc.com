<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\EntityUser;

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
            $activeEntityId = session('active_entity_id');
            $activePlantId  = session('active_plant_id');

            // --- Auto Setup Session if missing ---
            if (!$activeEntityId) {
                if ($user->isSystemAdmin()) {
                    $defaultPlant = \App\Models\Plant::first();
                    if ($defaultPlant) {
                        $activeEntityId = $defaultPlant->entity_id;
                        $activePlantId  = $defaultPlant->id;
                    }
                } else {
                    $defaultAccess = EntityUser::where('user_id', $user->id)->first();
                    if ($defaultAccess) {
                        $activeEntityId = $defaultAccess->entity_id;
                        $activePlantId  = $defaultAccess->plant_id;
                    }
                }

                if ($activeEntityId) {
                    session([
                        'active_entity_id' => $activeEntityId,
                        'active_plant_id'  => $activePlantId,
                    ]);
                }
            }
            // -------------------------------------

            if ($activeEntityId) {
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
        // it means the session is invalid or the user has no access. Logout immediately.
        if (Auth::check() && !session()->has('active_plant_id')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route('login')->withErrors([
                'email' => 'Session expired or plant context missing. Please login again.'
            ]);
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
