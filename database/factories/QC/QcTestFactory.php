<?php

namespace Database\Factories\QC;

use App\Models\QC\QcTest;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Plant;
use App\Models\QC\QcSample;
use App\Models\QC\QcTestType;
use App\Models\User;

/**
 * @extends Factory<QcTest>
 */
class QcTestFactory extends Factory
{
    protected $model = QcTest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'sample_id' => QcSample::factory(),
            'test_type_id' => QcTestType::factory(),
            'test_no' => $this->faker->unique()->numerify('TEST-####'),
            'scheduled_date' => now()->addDays(7),
            'age_days' => 7,
            'target_strength' => 20.0,
            'min_strength' => 15.0,
            'unit' => 'MPa',
            'overall_status' => 'pending',
            'created_by' => User::factory(),
        ];
    }
}
