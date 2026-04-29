<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'parent_id' => null,
            'name' => $this->faker->words(2, true),
            'code' => $this->faker->unique()->bothify('CAT-###'),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'status' => true,
        ];
    }
}
