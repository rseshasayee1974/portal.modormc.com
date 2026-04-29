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

        // Explicit bypass for SaaS Owner and Super Admin roles
        if ($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin')) {
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
        }
    }
}

