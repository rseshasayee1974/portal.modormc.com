<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Plant;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $plant = Plant::exists() ? Plant::inRandomOrder()->first() : null;

        return [
            'plant_id'        => $plant?->id ?? 1,
            'ref_no'          => 'EXP-' . $this->faker->unique()->numberBetween(1000, 9999),
            'expense_type_id' => ExpenseType::exists() ? ExpenseType::inRandomOrder()->first()->id : 1,
            'made_by'         => User::exists() ? User::inRandomOrder()->first()->id : null,
            'paid_through'    => Ledger::exists() ? Ledger::inRandomOrder()->first()->id : 1,
            'amount'          => $this->faker->randomFloat(2, 100, 5000),
            'date'            => $this->faker->dateTimeBetween('-6 months', 'now'),
            'note'            => $this->faker->optional()->sentence(),
            'status'          => true,
        ];
    }
}
