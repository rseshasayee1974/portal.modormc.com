<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DispatchFactory extends Factory
{
    protected $model = Dispatch::class;

    public function definition(): array
    {
        $workOrder = WorkOrder::query()->inRandomOrder()->first() ?? WorkOrder::factory()->create();
        $truckId = Machine::query()->inRandomOrder()->value('id') ?? Machine::factory()->create(['plant_id' => $workOrder->plant_id])->id;
        $batch = Batch::query()->where('work_order_id', $workOrder->id)->inRandomOrder()->first()
            ?? Batch::query()->create([
                'work_order_id' => $workOrder->id,
                'batch_no' => 1,
                'batch_size' => 1,
                'truck_id' => $truckId,
                'status' => Batch::STATUS_PLANNED,
            ]);

        return [
            'work_order_id' => $workOrder->id,
            'batch_id' => $batch->id,
            'truck_id' => $batch->truck_id,
            'driver_id' => Patron::query()->where('plant_id', $workOrder->plant_id)->inRandomOrder()->value('id'),
            'dispatch_time' => now(),
            'delivered_qty' => $this->faker->randomFloat(3, 0.5, 6),
        ];
    }
}
