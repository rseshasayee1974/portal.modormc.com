<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BankAccountType;

class BankAccountTypeFactory extends Factory
{
    protected $model = BankAccountType::class;

    public function definition(): array
    {
        return [
            'type' => fake()->word(),
        ];
    }
}