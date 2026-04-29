<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PaymentStatus;

class PaymentStatusFactory extends Factory
{
    protected $model = PaymentStatus::class;

    public function definition(): array
    {
        return [
            'status_name' => fake()->name(),
        ];
    }
}