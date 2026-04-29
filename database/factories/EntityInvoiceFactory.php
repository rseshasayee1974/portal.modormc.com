<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntityInvoice;

class EntityInvoiceFactory extends Factory
{
    protected $model = EntityInvoice::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'subscription_id' => 1,
            'invoice_no' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'tax_amount' => fake()->randomFloat(2, 0, 1000),
            'currency_id' => 1,
            'invoice_status' => fake()->numberBetween(1, 100),
            'issued_at' => now(),
            'due_date' => now(),
            'paid_at' => now(),
            'created_by' => fake()->numberBetween(1, 100),
            'updated_by' => fake()->numberBetween(1, 100),
            'deleted_by' => fake()->numberBetween(1, 100),
        ];
    }
}