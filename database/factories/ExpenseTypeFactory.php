<?php

namespace Database\Factories;

use App\Models\ExpenseType;
use App\Models\Plant;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseTypeFactory extends Factory
{
    protected $model = ExpenseType::class;

    public function definition(): array
    {
        return [
            'plant_id'  => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'name'      => $this->faker->randomElement(['Fuel', 'Office Supplies', 'Maintenance', 'Transport', 'Utilities', 'Miscellaneous']),
            'ledger_id' => Ledger::exists() ? Ledger::inRandomOrder()->first()->id : null,
            'status'    => true,
        ];
    }
}
