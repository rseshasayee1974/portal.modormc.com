<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SubscriptionStatus;

class SubscriptionStatusFactory extends Factory
{
    protected $model = SubscriptionStatus::class;

    public function definition(): array
    {
        return [
            'status_name' => fake()->unique()->randomElement(['active', 'trial', 'canceled', 'expired', 'archived']),
        ];
    }
}
