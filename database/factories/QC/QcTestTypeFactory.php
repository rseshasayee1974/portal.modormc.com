<?php

namespace Database\Factories\QC;

use App\Models\QC\QcTestType;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Plant;
use App\Models\User;

/**
 * @extends Factory<QcTestType>
 */
class QcTestTypeFactory extends Factory
{
    protected $model = QcTestType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'code' => $this->faker->unique()->lexify('TEST_????'),
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->randomElement(['Concrete', 'Aggregate', 'Cement']),
            'material_type' => 'Concrete',
            'standard_reference' => 'IS 516',
            'calculation_type' => 'formula',
            'layout_type' => 'SINGLE_TRIAL',
            'specimen_count' => 3,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
