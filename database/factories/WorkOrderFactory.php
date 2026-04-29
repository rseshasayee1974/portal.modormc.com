<?php

namespace Database\Factories;

use App\Models\MixDesign;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Site;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        $plant = Plant::query()->inRandomOrder()->first() ?? Plant::factory()->create();
        $customer = Patron::query()->where('plant_id', $plant->id)->inRandomOrder()->first()
            ?? Patron::factory()->create(['plant_id' => $plant->id, 'patron_type' => ['Customer']]);
        $site = Site::query()->where('plant_id', $plant->id)->inRandomOrder()->first()
            ?? Site::factory()->create(['plant_id' => $plant->id]);
        $mixDesign = MixDesign::query()->where('plant_id', $plant->id)->inRandomOrder()->first()
            ?? MixDesign::factory()->create(['plant_id' => $plant->id]);

        return [
            'prefix' => 'WO',
            'order_no' => WorkOrder::generateOrderNo('WO'),
            'plant_id' => $plant->id,
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'mix_design_id' => $mixDesign->id,
            'total_qty' => $this->faker->randomFloat(3, 10, 100),
            'produced_qty' => 0,
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDays(2),
            'status' => WorkOrder::STATUS_SCHEDULED,
        ];
    }
}
