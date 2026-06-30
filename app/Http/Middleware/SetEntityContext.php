<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Entity;
use App\Models\EntityUser;
use App\Models\Plant;
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

        if (!Auth::check()) {
            return $next($request);
        }

        $user         = Auth::user();
        $isSuperAdmin = $user->isSystemAdmin(); // Cache — avoids repeated role-relation lookups

        /** @var PlantContextService $ctx */
        $ctx            = app(PlantContextService::class);
        $activeEntityId = $ctx->entityId();
        $activePlantId  = $ctx->plantId();

        // --- Auto Setup Session if missing ---
        if (!$activeEntityId || !$activePlantId) {
            [$activeEntityId, $activePlantId] = $this->resolveDefaultContext($user, $isSuperAdmin);

            if ($activeEntityId && $activePlantId) {
                session([
                    'active_entity_id' => $activeEntityId,
                    'active_plant_id'  => $activePlantId,
                ]);
            }
        }

        // --- Apply entity/plant context ---
        if ($activeEntityId) {
            // Check if the entity is suspended (non-admins only)
            if (!$isSuperAdmin) {
                $isSuspended = Entity::where('id', $activeEntityId)
                    ->where('is_suspended', '!=', 0)
                    ->exists();

                if ($isSuspended) {
                    session()->forget(['active_entity_id', 'active_plant_id']);
                    return redirect()->route('entity-context.index');
                }
            }

            // Check if the active plant is inactive (non-admins only)
            if ($activePlantId && !$isSuperAdmin) {
                $isInactive = Plant::where('id', $activePlantId)
                    ->where('is_active', '!=', 1)
                    ->exists();

                if ($isInactive) {
                    session()->forget('active_plant_id');
                    return redirect()->route('entity-context.index');
                }
            }

            // Resolve entity-user role assignment for this request
            $this->assignRequestRole($user, $activeEntityId, $activePlantId, $isSuperAdmin);
        } else {
            // No entity set — clear all roles (unless system admin)
            if (!$isSuperAdmin) {
                $this->clearUserRoles($user);
            }
        }

        // --- FINAL SECURITY CHECK ---
        // If we still don't have an active_plant_id, redirect to selection page.
        if (!session()->has('active_plant_id')) {
            return redirect()->route('entity-context.index');
        }

        // Track last_visit_page for full Inertia page visits (GET requests only)
        $this->trackLastVisitPage($request, $user);

        return $next($request);
    }

    /**
     * Resolve default entity+plant when session is empty.
     *
     * Priority: user-saved defaults → single-choice auto-select → null.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveDefaultContext($user, bool $isSuperAdmin): array
    {
        // 1. Check for user-defined defaults
        if ($user->default_entity_id && $user->default_plant_id) {
            return [$user->default_entity_id, $user->default_plant_id];
        }

        // 2. Check for "Only One Choice" auto-select scenario
        if ($isSuperAdmin) {
            $entities = Entity::select('id')->limit(2)->get();
        } else {
            $entities = EntityUser::where('user_id', $user->id)
                ->select('entity_id')
                ->groupBy('entity_id')
                ->limit(2)
                ->get();
        }

        if ($entities->count() !== 1) {
            return [null, null];
        }

        $singleEntityId = $isSuperAdmin
            ? $entities->first()->id
            : $entities->first()->entity_id;

        // Count plants for this single entity
        $plantsQuery = Plant::where('entity_id', $singleEntityId)
            ->where('is_active', true)
            ->select('id');

        if (!$isSuperAdmin) {
            $assignments = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $singleEntityId)
                ->pluck('plant_id');

            $hasGlobalAccess = $assignments->contains(null);
            if (!$hasGlobalAccess) {
                $plantsQuery->whereIn('id', $assignments->filter());
            }
        }

        $plants = $plantsQuery->limit(2)->get();

        if ($plants->count() === 1) {
            return [$singleEntityId, $plants->first()->id];
        }

        return [null, null];
    }

    /**
     * Assign the contextual Spatie role for this request lifecycle only.
     * Caches the role assignment and loaded permissions to avoid DB queries
     * on every request, unless the role or permission models or user-role mapping changes.
     */
    private function assignRequestRole($user, int $entityId, ?int $plantId, bool $isSuperAdmin): void
    {
        $userId = $user->id;
        $userVersion = EntityUser::getContextVersion($userId);
        $globalVersion = EntityUser::getGlobalRolesVersion();

        $cacheKey = "role_ctx_{$userId}_{$entityId}_" . ($plantId ?? 0) . "_{$userVersion}_{$globalVersion}";

        $role = Cache::remember($cacheKey, now()->addDay(), function () use ($userId, $entityId, $plantId) {
            $entityUser = EntityUser::with(['role.permissions'])
                ->where('user_id', $userId)
                ->where('entity_id', $entityId)
                ->when($plantId, fn($q) => $q->where('plant_id', $plantId))
                ->first();

            return $entityUser && $entityUser->role ? $entityUser->role : null;
        });

        if ($role) {
            // Request-scoped role assignment only (NO DB writes)
            $user->unsetRelation('roles');
            $user->setRelation('roles', collect([$role]));
        } elseif (!$isSuperAdmin) {
            // No role for this entity/plant — clear roles
            $this->clearUserRoles($user);
        }
        // Super admins keep their existing System Administrator role
    }

    /**
     * Clear all in-memory roles from the user model.
     */
    private function clearUserRoles($user): void
    {
        $user->unsetRelation('roles');
        $user->setRelation('roles', collect());
    }

    /**
     * Track last visited page for Inertia GET requests.
     * Uses a direct DB update to avoid model event overhead and
     * is wrapped in try-catch to prevent race condition failures
     * from blocking the request.
     */
    private function trackLastVisitPage(Request $request, $user): void
    {
        if (!$request->header('X-Inertia') || !$request->isMethod('GET')) {
            return;
        }

        $currentUrl = $request->path();
        if ($user->last_visit_page === $currentUrl) {
            return;
        }

        try {
            DB::table('mm_users')
                ->where('id', $user->id)
                ->update(['last_visit_page' => $currentUrl]);

            // Keep in-memory model in sync
            $user->last_visit_page = $currentUrl;
        } catch (\Throwable $e) {
            // Non-critical — log and continue. Don't let a race condition
            // on this cosmetic field break the entire request.
            Log::warning('Failed to update last_visit_page', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
