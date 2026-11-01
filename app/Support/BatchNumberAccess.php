<?php

namespace App\Support;

use App\Models\EntityUser;
use App\Models\User;

class BatchNumberAccess
{
    public static function allows(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        $activeRole = null;
        if ($entityId = session('active_entity_id')) {
            $activeRole = EntityUser::forContext(
                $user->id, (int) $entityId, session('active_plant_id')
            )?->role;
        }

        if ($activeRole?->grantsFullAccess()) {
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
