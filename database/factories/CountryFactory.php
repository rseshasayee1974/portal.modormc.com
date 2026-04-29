<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Country;

class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'country_name' => fake()->name(),
            'country_code' => fake()->word(),
            'is_active' => fake()->boolean(),
        ];
    }
}