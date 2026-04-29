<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityTax;

class EntityTaxFactory extends Factory
{
    protected $model = EntityTax::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'tax_type' => fake()->word(),
            'tax_number' => fake()->word(),
            'country_id' => 1,
            'state_id' => 1,
            'is_primary' => fake()->boolean(),
            'created_by' => fake()->numberBetween(1, 100),
            'updated_by' => fake()->numberBetween(1, 100),
            'deleted_by' => fake()->numberBetween(1, 100),
        ];
    }
}