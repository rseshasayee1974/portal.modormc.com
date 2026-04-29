<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Plant;
use App\Models\Ledger;
use App\Models\Patron;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'ledger_id' => Ledger::exists() ? Ledger::inRandomOrder()->first()->id : 1,
            'patron_id' => Patron::exists() ? Patron::inRandomOrder()->first()->id : null,
            'amount' => $this->faker->randomFloat(2, 500, 50000),
            'transaction_type' => $this->faker->randomElement(['payment', 'receipt']),
            'description' => $this->faker->sentence(),
            'reference' => 'TXN-' . $this->faker->numerify('#####'),
            'status' => $this->faker->randomElement(['pending', 'completed']),
        ];
    }
}
