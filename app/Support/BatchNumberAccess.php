<?php

namespace App\Support;

use App\Models\EntityUser;
use App\Models\Role;
use App\Models\User;

class BatchNumberAccess
{
    public static function allows(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $adminRoles = [
            'Saas Owner', 'Platform Admin', 'Super Admin',
            'Super Administrator', 'Administrator', 'Admin',
        ];

        if ($user->hasAnyRole($adminRoles)) {
            return true;
        }

        $activeRole = null;
        if ($entityId = session('active_entity_id')) {
            $roleId = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $entityId)
                ->value('role_id');
            $activeRole = $roleId ? Role::with('permissions')->find($roleId) : null;
        }

        if ($activeRole && in_array($activeRole->name, $adminRoles, true)) {
            return true;
        }

        // Match the active entity permissions shared with the batch forms.
        $permissions = $activeRole
            ? $activeRole->permissions->merge($user->getDirectPermissions())
            : $user->getAllPermissions();

        return $permissions->contains(fn ($permission) => in_array(
            strtoupper($permission->name),
            ['BATCH.CREATE', 'BATCHES.CREATE'],
            true,
        ));
    }
}
