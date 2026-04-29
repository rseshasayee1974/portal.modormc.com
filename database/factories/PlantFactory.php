<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plant>
 */
class PlantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => \App\Models\Entity::first()?->id ?? \App\Models\Entity::factory(),
            'code' => $this->faker->unique()->bothify('PLT-####'),
            'name' => $this->faker->company . ' Plant',
            'gstin' => $this->faker->bothify('##???????#####'),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'is_main' => $this->faker->boolean(),
            'is_active' => true,
        ];
    }
}
