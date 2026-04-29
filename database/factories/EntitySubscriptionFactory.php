<?php

namespace Database\Factories;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EntitySubscription;
use App\Models\Plan;
use App\Models\SubscriptionStatus;

class EntitySubscriptionFactory extends Factory
{
    protected $model = EntitySubscription::class;

    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'plan_id' => Plan::factory(),
            'scheduled_plan_id' => null,
            'subscription_status_id' => SubscriptionStatus::factory(),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'started_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
            'scheduled_change_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
