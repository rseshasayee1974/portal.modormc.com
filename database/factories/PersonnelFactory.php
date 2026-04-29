<?php

namespace Database\Factories;

use App\Models\Personnel;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Personnel>
 */
class PersonnelFactory extends Factory
{
    protected $model = Personnel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plant_id' => 1,
            'plant_id' => Plant::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'employee_type' => $this->faker->randomElement(['Permanent', 'Contract', 'Daily Wage']),
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'date_of_birth' => $this->faker->date(),
            'joining_date' => $this->faker->date(),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
