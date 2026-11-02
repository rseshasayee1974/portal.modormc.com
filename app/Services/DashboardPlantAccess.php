<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 13:44:34 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardPlantAccess
{
    public function canViewAllPlants(?User $user): bool
    {
        return $user && $user->roles->contains(function ($role) {
            $code = strtoupper(trim((string) $role->code));
            if ($code === '') {
                $code = strtoupper(str_replace(' ', '_', trim((string) $role->name)));
                if ($code === 'SUPER_ADMINISTRATOR') {
                    $code = Role::SUPER_ADMIN;
                }
            }

            return in_array($code, [Role::SUPER_ADMIN, Role::SAAS_OWNER], true);
        });
    }

    public function query(?User $user = null): Builder
    {
        $user ??= auth()->user();
        $plants = Plant::query()->where('is_active', 1);
        if (!$user) {
            return $plants->whereRaw('1 = 0');
        }
        if ($this->canViewAllPlants($user)) {
            return $plants;
        }

        // Entity-wide assignments grant that entity's plants, never other entities.
        return $plants->whereHas('entity', fn ($entity) => $entity->where('is_suspended', 0))
            ->whereExists(function ($assignment) use ($user) {
                $assignment->selectRaw('1')->from('mm_entity_users')
                    ->where('user_id', $user->id)
                    ->whereNull('mm_entity_users.deleted_at')
                    ->whereColumn('mm_entity_users.entity_id', 'mm_plants.entity_id')
                    ->where(function ($scope) {
                        $scope->whereNull('mm_entity_users.plant_id')
                            ->orWhereColumn('mm_entity_users.plant_id', 'mm_plants.id');
                    });
            });
    }

    public function resolve(Request $request): int
    {
        $user = $request->user();
        abort_unless($user, 401);
        if ($request->filled('plant_id')) {
            $plantId = filter_var($request->input('plant_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_if($plantId === false, 422, 'Select a valid plant.');
            abort_unless($this->query($user)->whereKey($plantId)->exists(), 403, 'You do not have access to this plant.');

            return $plantId;
        }

        $plantId = $request->session()->get('active_plant_id') ?: $user->default_plant_id;
        if ($plantId && $this->query($user)->whereKey($plantId)->exists()) {
            return (int) $plantId;
        }

        $plantId = $this->query($user)->orderBy('id')->value('id');
        abort_unless($plantId, 403, 'No accessible plant is available for this dashboard.');

        return (int) $plantId;
    }

    public function scope($query, string $plantColumn, array $filters)
    {
        $query->whereIn($plantColumn, $this->query()->select('mm_plants.id'));
        if (isset($filters['plant_id']) && $filters['plant_id'] !== '') {
            $query->where($plantColumn, $filters['plant_id']);
        }

        return $query;
    }
}
