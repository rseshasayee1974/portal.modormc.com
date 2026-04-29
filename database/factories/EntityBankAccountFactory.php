<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityBankAccount;

class EntityBankAccountFactory extends Factory
{
    protected $model = EntityBankAccount::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'account_type' => fake()->numberBetween(1, 100),
            'account_number' => fake()->word(),
            'bank_name' => fake()->name(),
            'bank_branch' => fake()->word(),
            'ifsc_code' => fake()->word(),
            'bank_address' => fake()->address(),
            'is_primary' => fake()->boolean(),
            'created_by' => fake()->numberBetween(1, 100),
            'updated_by' => fake()->numberBetween(1, 100),
            'deleted_by' => fake()->numberBetween(1, 100),
        ];
    }
}