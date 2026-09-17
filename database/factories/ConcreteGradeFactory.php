<?php

namespace Database\Factories;

use App\Models\ConcreteGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConcreteGrade>
 */
class ConcreteGradeFactory extends Factory
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
            'name' => 'M' . $this->faker->numberBetween(10, 80),
            'status' => true,
        ];
    }
}
