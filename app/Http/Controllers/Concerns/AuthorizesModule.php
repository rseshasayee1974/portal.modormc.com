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
            if ($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin')) {
                return;
            }
            abort(403, "Access Denied: Master data is restricted to SaaS Owners.");
        }

        // Explicit bypass for SaaS Owner, Super Admin, and Administrator roles for other modules
        if ($user->isSystemAdmin()) {
            return;
        }

        // Map common controller actions to the new uppercase actions
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
        
        // Ensure module name is singular and uppercase (e.g., 'users' -> 'USER', 'Role' -> 'ROLE')
        $module = strtoupper(\Illuminate\Support\Str::singular($this->module));
        
        $permission = "{$module}.{$mappedAction}";

        if (\Illuminate\Support\Facades\Gate::denies($permission)) {
            abort(403, "Access Denied: You do not have the required permission ({$permission}) for the {$module} module.");
            // abort(403, "Access Denied: You do not have the required permission for the {$module} module.");
        }
    }
}

