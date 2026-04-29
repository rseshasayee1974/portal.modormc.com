<?php

namespace Database\Factories;

use App\Models\Tax;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'tax_name' => $this->faker->unique()->word() . ' Tax',
            'tax_type' => $this->faker->randomElement(['sales', 'purchase', 'other sales', 'other purchase', 'others']),
            'tax_group' => $this->faker->randomElement(['GST', 'CGST', 'SGST', 'IGST', 'TDS', 'TCS', 'CESS', 'OTHER']),
            'tax_rate' => $this->faker->randomElement([0, 0.1, 1, 3, 5, 12, 18, 28]),
            'parent_id' => null,
            'account_id' => null,
            'status' => 1,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
