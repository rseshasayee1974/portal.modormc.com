<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityAddress;

class EntityAddressFactory extends Factory
{
    protected $model = EntityAddress::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'address_type' => fake()->numberBetween(1, 100),
            'line_1' => fake()->word(),
            'line_2' => fake()->word(),
            'city' => fake()->city(),
            'zipcode' => fake()->postcode(),
            'landmark' => fake()->word(),
            'country_id' => 1,
            'state_id' => 1,
            'is_primary' => fake()->boolean(),
            'created_by' => fake()->numberBetween(1, 100),
            'updated_by' => fake()->numberBetween(1, 100),
            'deleted_by' => fake()->numberBetween(1, 100),
        ];
    }
}