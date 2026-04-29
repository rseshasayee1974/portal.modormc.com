<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\StateCode;

class StateCodeFactory extends Factory
{
    protected $model = StateCode::class;

    public function definition(): array
    {
        return [
            'country_id' => 1,
            'state_code' => fake()->word(),
            'state_name' => fake()->name(),
        ];
    }
}