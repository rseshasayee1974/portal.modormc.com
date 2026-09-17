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
    protected function authorizeModule(string $action): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        // Restrict master and its submenus strictly to SaaS Owner and Platform Admin (blocking Super Admin)
        $prefix = strtoupper(\Illuminate\Support\Str::singular($this->module));
        $isMasterModule = false;
        try {
            $isMasterModule = \App\Models\Menu::where(function ($q) {
                    $q->where('id', 2)->orWhere('parent_id', 2);
                })
                ->where('permission_name', 'like', $prefix . '.%')
                ->exists();
        } catch (\Exception $e) {
            // Safe fallback if database is not fully loaded/migrated yet
        }

        if ($isMasterModule) {
            if ($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('SAAS_OWNER') || $user->hasRole('PLATFORM_ADMIN')) {
                return;
            }
            abort(403, "Access Denied: Master data is restricted to SaaS Owners.");
        }

        // Explicit bypass for SaaS Owner, Super Admin, and Administrator roles for other modules
        if ($user->isSystemAdmin()) {
            return;
        }

        // Fetch active entity role for user if available
        $activeEntityId = session('active_entity_id');
        $activeRole = null;
        if ($activeEntityId) {
            $entityUser = \App\Models\EntityUser::where('user_id', $user->id)
                ->where('entity_id', $activeEntityId)
                ->first();
            if ($entityUser && $entityUser->role_id) {
                $activeRole = \App\Models\Role::with('permissions')->find($entityUser->role_id);
            }
        }

        if ($activeRole) {
            $adminRoleCodes = ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'];
            $adminRoleNames = ['Saas Owner', 'Platform Admin', 'Super Admin', 'Super Administrator', 'Administrator'];
            if (in_array(strtoupper($activeRole->code ?? ''), $adminRoleCodes) || in_array($activeRole->name ?? '', $adminRoleNames)) {
                return;
            }
        }

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
        $singular = strtoupper(\Illuminate\Support\Str::singular($this->module));
        $plural   = strtoupper(\Illuminate\Support\Str::plural($this->module));
        
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
            abort(403, "Access Denied: You do not have the required permission ({$permissionPlural}) for the {$this->module} module.");
        }
    }
}
