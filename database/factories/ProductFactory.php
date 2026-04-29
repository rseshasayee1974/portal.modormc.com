<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
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
            'category_id' => \App\Models\ProductCategory::factory(),
            'unit_id' => \App\Models\ProductUnit::take(1)->first()?->id ?? \App\Models\ProductUnit::factory(),
            'is_service' => $this->faker->boolean(),
            'purchase_price' => $this->faker->randomFloat(2, 10, 500),
            'sales_price' => $this->faker->randomFloat(2, 20, 1000),
            'title' => $this->faker->unique()->sentence(3),
            'material_code' => $this->faker->bothify('MAT-###-???'),
            'product_type' => $this->faker->randomElement(['Purchase', 'Sales', 'Production', 'Other']),
            'code' => $this->faker->unique()->bothify('PRD-###'),
            'alias' => $this->faker->word(),
            'description' => $this->faker->paragraph(),
            'is_returnable' => $this->faker->boolean(),
            'status' => true,
            'created_by' => \App\Models\User::first()?->id ?? \App\Models\User::factory(),
        ];
    }
}
