<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:22:29 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

use App\Models\EntityUser;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;

final class GstAccess
{
    public const ACTIONS = ['VIEW', 'IMPORT', 'PREPARE', 'REVIEW', 'EXPORT', 'TALLY_IMPORT', 'TALLY_POST'];

    public function permissions(User $user, int $plant): array
    {
        return $this->forModule($user, $plant, 'GST_COMPLIANCE', self::ACTIONS);
    }

    public function forModule(User $user, int $plant, string $module, array $actions): array
    {
        $p = Plant::findOrFail($plant);
        abort_unless((int) $p->entity_id === (int) session('active_entity_id'), 403, 'Plant does not belong to selected entity');
        if ($user->isSystemAdmin()) {
            return array_fill_keys($actions, true);
        }
        $assignment = EntityUser::where('user_id', $user->id)->where('entity_id', $p->entity_id)
            ->where(fn ($q) => $q->whereNull('plant_id')->orWhere('plant_id', $plant))
            ->orderByRaw('CASE WHEN plant_id IS NULL THEN 1 ELSE 0 END')->first();
        abort_unless($assignment, 403, 'No access to selected plant');
        $role = $assignment->role_id ? Role::with('permissions')->find($assignment->role_id) : null;
        $admin = $role && in_array(strtoupper($role->code ?? ''), ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'], true);
        $grants = ($role ? $role->permissions : collect())->merge($user->getDirectPermissions())->pluck('name')->map(fn ($p) => strtoupper($p));
        $out = [];
        foreach ($actions as $a) {
            $out[$a] = $admin || $grants->contains($module.'.'.$a);
        }
        // A write permission alone never reveals a workspace.
        if (! $out['VIEW']) {
            foreach ($out as $a => $_) {
                $out[$a] = false;
            }
        }

        return $out;
    }
}