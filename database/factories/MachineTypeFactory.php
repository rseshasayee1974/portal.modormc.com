<?php

namespace Database\Factories;

use App\Models\MachineType;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MachineTypeFactory extends Factory
{
    protected $model = MachineType::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'name'     => $this->faker->unique()->randomElement([
                'Bulldozer', 'Excavator', 'Dump Truck', 'Compactor', 
                'Crane', 'Loader', 'Grader', 'Concrete Mixer', 'Roller'
            ]),
        ];
    }
}
