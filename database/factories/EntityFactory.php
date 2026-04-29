<?php

namespace Database\Factories;

use App\Models\EntityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Entity;

class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        return [
            'entity_type' => EntityType::factory(),
            'parent_id' => null,
            'legal_name' => fake()->company(),
            'alias' => fake()->word(),
            'email' => fake()->unique()->safeEmail(),
            'url' => fake()->url(),
            'logo_file' => null,
            'description' => fake()->text(),
            'time_zone' => fake()->timezone(),
            'is_active' => 1,
            'is_suspended' => 0,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
