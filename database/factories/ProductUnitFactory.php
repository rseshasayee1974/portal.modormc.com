<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $units = [
            'Kilogram' => 'kg',
            'Gram' => 'gm',
            'Piece' => 'pcs',
            'Liter' => 'ltr',
            'Meter' => 'm',
            'Foot' => 'ft',
            'Yard' => 'yd',
            'Box' => 'box',
            'Pack' => 'pk',
            'Roll' => 'rl',
            'Set' => 'set',
            'Unit' => 'u',
        ];

        $name = array_rand($units);

        return [
            'unit_type' => $this->faker->randomElement(['Sales', 'Purchase', 'Production', 'Inventory', 'Retail', 'Wholesale', 'Others']),
            'unit_name' => $name,
            'unit_code' => $units[$name],
        ];
    }
}
