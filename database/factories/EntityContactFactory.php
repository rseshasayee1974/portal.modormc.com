<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityContact;

class EntityContactFactory extends Factory
{
    protected $model = EntityContact::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'contact_type' => fake()->numberBetween(1, 100),
            'contact_person' => fake()->word(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->word(),
            'alt_mobile' => fake()->word(),
            'landline' => fake()->word(),
            'is_primary' => fake()->boolean(),
            'created_by' => fake()->numberBetween(1, 100),
            'updated_by' => fake()->numberBetween(1, 100),
            'deleted_by' => fake()->numberBetween(1, 100),
        ];
    }
}