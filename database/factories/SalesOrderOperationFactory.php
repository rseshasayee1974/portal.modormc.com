<?php

namespace Database\Factories;

use App\Models\SalesOrderOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderOperationFactory extends Factory
{
    protected $model = SalesOrderOperation::class;

    public function definition(): array
    {
        return [
            'operation_name' => $this->faker->words(2, true),
            'sequence' => $this->faker->numberBetween(1, 10),
            'duration' => $this->faker->numberBetween(30, 480),
            'status' => 1,
            'created' => now(),
            'created_by' => 1,
        ];
    }
}
