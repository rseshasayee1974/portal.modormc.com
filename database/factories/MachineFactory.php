<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Machine>
 */
class MachineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration' => strtoupper($this->faker->bothify('?? ## ?? ####')),
            'vehicle_model' => $this->faker->randomElement(['Ashok Leyland 2518', 'Tata Prima 2525', 'BharatBenz 2523R', 'JCB 3DX', 'Komatsu PC210']),
            'make_year' => $this->faker->year(),
            'engine_no' => $this->faker->bothify('ENG#######'),
            'chassis_no' => $this->faker->bothify('CHS#######'),
            'vehicle_type' => $this->faker->randomElement(['Truck', 'JCB', 'Excavator', 'Bunker', 'Grader', 'Roller']),
            'capacity' => $this->faker->numberBetween(10, 30),
            'plant_id' => 1, // Will be overridden in seeder
        ];
    }
}
