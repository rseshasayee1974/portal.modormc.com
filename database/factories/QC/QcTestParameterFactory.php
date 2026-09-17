<?php

namespace Database\Factories\QC;

use App\Models\QC\QcTestParameter;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\QC\QcTestType;
use App\Models\User;

/**
 * @extends Factory<QcTestParameter>
 */
class QcTestParameterFactory extends Factory
{
    protected $model = QcTestParameter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_type_id' => QcTestType::factory(),
            'code' => $this->faker->unique()->lexify('PARAM_????'),
            'name' => $this->faker->words(2, true),
            'data_type' => 'numeric',
            'scope' => 'test',
            'is_required' => true,
            'is_calculated' => false,
            'is_summary' => false,
            'display_order' => 1,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
