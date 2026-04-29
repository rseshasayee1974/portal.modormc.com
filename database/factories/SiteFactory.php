<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => \App\Models\Plant::factory(),
            'name' => $this->faker->company . ' Site',
            'code' => $this->faker->unique()->bothify('SITE-####'),
            'type' => $this->faker->randomElement(['loading', 'unloading']),
            'is_restricted' => $this->faker->boolean(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
        ];
    }
}
