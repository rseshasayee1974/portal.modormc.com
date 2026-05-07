<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        WorkOrder::factory(10)->create()->each(function ($wo) {
            $truckId = \App\Models\Machine::query()->inRandomOrder()->value('id')
                    ?? \App\Models\Machine::factory()->create(['plant_id' => $wo->plant_id])->id;

            $batch = Batch::query()->create([
                'work_order_id' => $wo->id,
                'batch_no' => 1,
                'batch_size' => 1.5,
                'status' => Batch::STATUS_PLANNED,
            ]);

            Dispatch::factory()->create([
                'work_order_id' => $wo->id,
                'batch_id' => $batch->id,
                'truck_id' => $truckId,
            ]);

            $wo->update([
                'produced_qty' => 1.5,
                'status' => WorkOrder::STATUS_IN_PROGRESS,
            ]);
        });
    }
}
