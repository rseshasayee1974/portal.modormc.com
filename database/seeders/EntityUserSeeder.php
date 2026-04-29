<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\EntityUser;
use App\Models\User;
use App\Models\Entity;
use App\Models\Plant;
use Spatie\Permission\Models\Role;

class EntityUserSeeder extends Seeder
{
    /**
     * Seed mm_entity_users with realistic entity + plant + role assignments.
     */
    public function run(): void
    {
        // ── 0. Truncate ──────────────────────────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        EntityUser::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── 1. Load reference data ────────────────────────────────────────────────
        $superAdminRole = Role::whereIn('code', ['SAAS_OWNER', 'SUPER_ADMIN'])->first() 
                          ?? Role::where('name', 'Super Administrator')->first();
        $defaultRole    = Role::whereNotIn('code', ['SAAS_OWNER', 'SUPER_ADMIN'])
                              ->where('name', '!=', 'Super Administrator')
                              ->orderBy('level', 'desc') // lowest privilege = highest level number
                              ->first();

        $entities = Entity::where('is_active', true)->get();
        $users    = User::all();

        if ($entities->isEmpty()) {
            $this->command->warn('No active entities found — skipping EntityUserSeeder.');
            return;
        }

        if (!$superAdminRole && !$defaultRole) {
            $this->command->warn('No roles found — skipping EntityUserSeeder.');
            return;
        }

        // ── 2. Super Admin user → all entities × plants ───────────────────────────
        $superAdminUser = $users->first(fn ($u) => $u->hasRole('Super Administrator') || $u->hasRole('Saas Owner'));

        if ($superAdminUser && $superAdminRole) {
            foreach ($entities as $entity) {
                $plants = Plant::where('entity_id', $entity->id)
                               ->where('is_active', true)
                               ->get();

                if ($plants->isEmpty()) {
                    EntityUser::firstOrCreate(
                        [
                            'user_id'   => $superAdminUser->id,
                            'entity_id' => $entity->id,
                            'plant_id'  => null,
                        ],
                        [
                            'role_id'    => $superAdminRole->id,
                            'created_by' => $superAdminUser->id,
                        ]
                    );
                } else {
                    foreach ($plants as $plant) {
                        EntityUser::firstOrCreate(
                            [
                                'user_id'   => $superAdminUser->id,
                                'entity_id' => $entity->id,
                                'plant_id'  => $plant->id,
                            ],
                            [
                                'role_id'    => $superAdminRole->id,
                                'created_by' => $superAdminUser->id,
                            ]
                        );
                    }
                }
            }

            $this->command->info("Super Admin [{$superAdminUser->email}] assigned to all entities & plants.");
        }

        // ── 3. Normal users → one entity + its plants each ────────────────────────
        $roleForNormal = $defaultRole ?? $superAdminRole;
        $entityList    = $entities->values();
        $entityCount   = $entityList->count();

        $normalUsers = $users->filter(
            fn ($u) => !$u->hasRole('Super Administrator') && !$u->hasRole('Saas Owner')
        )->values();

        foreach ($normalUsers as $index => $user) {
            $entity = $entityList[$index % $entityCount];
            $plants = Plant::where('entity_id', $entity->id)
                           ->where('is_active', true)
                           ->get();

            if ($plants->isEmpty()) {
                EntityUser::firstOrCreate(
                    [
                        'user_id'   => $user->id,
                        'entity_id' => $entity->id,
                        'plant_id'  => null,
                    ],
                    [
                        'role_id'    => $roleForNormal->id,
                        'created_by' => $superAdminUser?->id ?? 1,
                    ]
                );
            } else {
                $plant = $plants->firstWhere('is_main', true) ?? $plants->first();

                EntityUser::firstOrCreate(
                    [
                        'user_id'   => $user->id,
                        'entity_id' => $entity->id,
                        'plant_id'  => $plant->id,
                    ],
                    [
                        'role_id'    => $roleForNormal->id,
                        'created_by' => $superAdminUser?->id ?? 1,
                    ]
                );
            }
        }

        $this->command->info(
            sprintf(
                'EntityUserSeeder complete — %d records created.',
                EntityUser::count()
            )
        );
    }
}
