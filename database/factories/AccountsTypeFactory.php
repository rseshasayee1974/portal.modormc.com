<?php

namespace Database\Factories;

use App\Models\Accounts;
use App\Models\AccountsType;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountsTypeFactory extends Factory
{
    protected $model = AccountsType::class;

    public function definition(): array
    {
        return [
            'plant_id' => \App\Models\Plant::factory(),
            'account_id' => \App\Models\Accounts::factory(),
            'code' => $this->faker->unique()->numerify('####'),
            'title' => $this->faker->words(2, true),
            'status' => 1,
            'created_at' => now(),
        ];
    }
}
