<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\AddressType;

class AddressTypeFactory extends Factory
{
    protected $model = AddressType::class;

    public function definition(): array
    {
        return [
            'type' => fake()->word(),
        ];
    }
}