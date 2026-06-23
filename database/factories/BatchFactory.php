<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Plant;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition()
    {
        return [
            'plant_id' => Plant::factory(),
            'sales_order_id' => \App\Models\SalesOrder::factory(),
            'batch_no' => $this->faker->unique()->numberBetween(1000, 9999),
            
            'batch_size' => $this->faker->randomFloat(2, 5, 20),
            'status' => Batch::STATUS_PLANNED,
        ];
    }
}
