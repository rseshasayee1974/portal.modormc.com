<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * Reusable permission check for all resource controllers.
 *
 * Each controller that uses this trait must define:
 *   protected string $module = 'users';
 *
 * Permission names follow the pattern: {module}.{action}
 * e.g. users.menu, users.create, users.edit, users.delete, users.show
 * or uppercase module patterns: BATCH.VIEW, BATCHES.VIEW
 */
trait AuthorizesModule
{
    /**
     * Abort with 403 if the current user lacks the given permission.
     */
    protected function authorizeModule(string $action, ?string $module = null): void
    {
        $module ??= $this->module;
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        if ($user->hasFullAccess()) {
            return;
        }

        // Fetch active entity role for user if available
        $activeEntityId = session('active_entity_id');
        $activeRole = null;
        if ($activeEntityId) {
            $entityUser = \App\Models\EntityUser::forContext($user->id, (int) $activeEntityId, session('active_plant_id'));
            if ($entityUser && $entityUser->role_id) {
                $activeRole = \App\Models\Role::with('permissions')->find($entityUser->role_id);
            }
        }

        if ($activeRole?->grantsFullAccess()) return;

        // Map common controller actions to uppercase actions
        $actionMap = [
            'menu'    => 'VIEW',
            'listing' => 'VIEW',
            'show'    => 'VIEW',
            'create'  => 'CREATE',
            'store'   => 'CREATE',
            'edit'    => 'UPDATE',
            'update'  => 'UPDATE',
            'destroy' => 'DELETE',
            'delete'  => 'DELETE',
        ];

        $mappedAction = $actionMap[$action] ?? strtoupper($action);
        
        // Check both singular and plural forms (e.g. BATCH.VIEW and BATCHES.VIEW)
        $singular = strtoupper(\Illuminate\Support\Str::singular($module));
        $plural   = strtoupper(\Illuminate\Support\Str::plural($module));
        
        $permissionSingular = "{$singular}.{$mappedAction}";
        $permissionPlural   = "{$plural}.{$mappedAction}";

        $tenantPermissions = collect();
        if ($activeRole) {
            $tenantPermissions = $activeRole->permissions->pluck('name');
            $tenantPermissions = $tenantPermissions->merge($user->getDirectPermissions()->pluck('name'))->unique()->values();
        } else {
            $tenantPermissions = $user->getAllPermissions()->pluck('name');
        }

        $upperTenantPermissions = $tenantPermissions->map(fn($p) => strtoupper($p));

        $hasPermission = $upperTenantPermissions->contains(strtoupper($permissionSingular))
            || $upperTenantPermissions->contains(strtoupper($permissionPlural))
            || \Illuminate\Support\Facades\Gate::allows($permissionSingular)
            || \Illuminate\Support\Facades\Gate::allows($permissionPlural);

        if (!$hasPermission) {
            abort(403, "Access Denied: You do not have the required permission ({$permissionPlural}) for the {$module} module.");
        }
    }
}
