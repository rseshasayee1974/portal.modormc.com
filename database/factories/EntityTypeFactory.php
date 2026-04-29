<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityType;

class EntityTypeFactory extends Factory
{
    protected $model = EntityType::class;

    public function definition(): array
    {
        return [
            'type' => fake()->word(),
        ];
    }
}