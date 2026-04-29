<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Plan;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'plan_type' => fake()->unique()->bothify('Plan-##??'),
            'price_monthly' => fake()->randomFloat(2, 0, 1000),
            'monthly_plan_description' => fake()->sentence(),
            'price_yearly' => fake()->randomFloat(2, 0, 1000),
            'yearly_plan_description' => fake()->sentence(),
            'max_users' => fake()->numberBetween(1, 100),
            'features_json' => [fake()->lexify('FEATURE_???')],
            'is_active' => fake()->boolean(),
        ];
    }
}
