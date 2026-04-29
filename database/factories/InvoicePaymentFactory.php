<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\InvoicePayment;

class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => 1,
            'gateway_id' => 1,
            'transaction_ref' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'payment_status_id' => 1,
            'paid_at' => now(),
            'created_by' => fake()->numberBetween(1, 100),
            'updated_by' => fake()->numberBetween(1, 100),
            'deleted_by' => fake()->numberBetween(1, 100),
        ];
    }
}