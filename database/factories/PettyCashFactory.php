<?php

namespace Database\Factories;

use App\Models\PettyCash;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PettyCashFactory extends Factory
{
    protected $model = PettyCash::class;

    public function definition(): array
    {
        return [
            'plant_id'        => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'ref_no'          => 'PC-' . $this->faker->unique()->numerify('######'),
            'prefix'          => 'PC',
            'date'            => $this->faker->dateTimeBetween('-3 months', 'now'),
            'opening_balance' => $this->faker->randomFloat(2, 5000, 50000),
            'closing_balance' => 0,
            'paid_by'         => User::exists() ? User::inRandomOrder()->first()->id : null,
            'paid_to'         => User::exists() ? User::inRandomOrder()->first()->id : null,
            'journal_status'  => false,
            'closed_status'   => false,
            'request_amount'  => $this->faker->optional()->randomFloat(2, 500, 10000),
            'status'          => true,
        ];
    }
}
