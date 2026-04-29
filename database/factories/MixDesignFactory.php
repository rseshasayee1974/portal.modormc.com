<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MixDesign>
 */
class MixDesignFactory extends Factory
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
            'grade' => $this->faker->randomElement(['M10', 'M20', 'M25', 'M30']),
            'design_name' => 'Design ' . $this->faker->unique()->bothify('##??'),
            'design_code' => 'MD-' . $this->faker->unique()->numerify('####'),
            'is_active' => true,
        ];
    }
}
