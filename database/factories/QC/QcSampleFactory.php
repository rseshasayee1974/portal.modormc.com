<?php

namespace Database\Factories\QC;

use App\Models\QC\QcSample;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Plant;
use App\Models\User;
use App\Models\ConcreteGrade;

/**
 * @extends Factory<QcSample>
 */
class QcSampleFactory extends Factory
{
    protected $model = QcSample::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'sample_no' => $this->faker->unique()->numerify('SMP-####'),
            'sample_date' => now(),
            'concrete_grade_id' => ConcreteGrade::factory(),
            'specimen_count' => 3,
            'status' => 'PENDING',
            'sampled_by' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
