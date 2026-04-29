<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\InvoiceStatus;

class InvoiceStatusFactory extends Factory
{
    protected $model = InvoiceStatus::class;

    public function definition(): array
    {
        return [
            'status_name' => fake()->name(),
        ];
    }
}