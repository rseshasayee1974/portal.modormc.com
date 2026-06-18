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
            'partner_id' => \App\Models\Patron::factory(),
            'concrete_grade_id' => function (array $attributes) {
                return \App\Models\ConcreteGrade::firstOrCreate(
                    ['plant_id' => $attributes['plant_id'], 'name' => 'M25'],
                    ['concrete_code' => 'M25-CODE']
                )->id;
            },
            'design_name' => 'Design ' . $this->faker->unique()->bothify('##??'),
            'design_code' => 'MD-' . $this->faker->unique()->numerify('####'),
        ];
    }
}
