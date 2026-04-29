<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityUser;
use App\Models\Entity;
use App\Models\Plant;
use Spatie\Permission\Models\Role;

class EntityUserFactory extends Factory
{
    protected $model = EntityUser::class;

    public function definition(): array
    {
        $entity = Entity::inRandomOrder()->first()
                  ?? Entity::factory()->create();

        $plant = Plant::where('entity_id', $entity->id)
                      ->where('is_active', true)
                      ->inRandomOrder()
                      ->first();

        $role = Role::inRandomOrder()->first();

        return [
            'user_id'    => 1,
            'plant_id' => 1,
            'plant_id'   => $plant?->id,      // nullable — plant may not exist yet
            'role_id'    => $role?->id ?? 1,
            'created_by' => 1,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}