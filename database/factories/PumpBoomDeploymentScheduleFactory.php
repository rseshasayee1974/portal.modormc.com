<?php

namespace Database\Factories;

use App\Models\PumpBoomDeploymentSchedule;
use App\Models\Plant;
use App\Models\Site;
use App\Models\Machine;
use App\Models\MixDesign;
use App\Models\Personnel;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class PumpBoomDeploymentScheduleFactory extends Factory
{
    protected $model = PumpBoomDeploymentSchedule::class;

    public function definition(): array
    {
        $plant = Plant::first() ?? Plant::factory()->create();

        return [
            'plant_id' => $plant->id,
            'schedule_date' => $this->faker->date(),
            'sales_order_id' => null,
            'site_id' => null,
            'site_name' => null,
            'pour_location' => 'Slab',
            'mix_design_id' => null,
            'grade' => null,
            'planned_qty_m3' => $this->faker->randomFloat(1, 10, 100),
            'pump_type' => 'boom_pump',
            'pump_vehicle_id' => null,
            'pump_no' => 'PUMP-' . $this->faker->numberBetween(1, 100),
            'boom_length_m' => 36.0,
            'operator_id' => null,
            'operator_name' => $this->faker->name,
            'status' => 'scheduled',
            'notes' => $this->faker->sentence,
        ];
    }

    public function withRelations(Plant $plant = null)
    {
        return $this->state(function (array $attributes) use ($plant) {
            $pId = $plant ? $plant->id : ($attributes['plant_id'] ?? 1);

            $site = Site::firstOrCreate(['plant_id' => $pId], ['name' => 'Test Site']);
            $machine = Machine::firstOrCreate(['plant_id' => $pId], ['registration' => 'TEST-PUMP', 'category' => 'Pump']);
            $operator = Personnel::firstOrCreate(['plant_id' => $pId], ['first_name' => 'John', 'last_name' => 'Doe']);
            $mix = MixDesign::firstOrCreate(['plant_id' => $pId], ['design_name' => 'M40', 'design_code' => 'M40-01']);
            $so = SalesOrder::firstOrCreate(['plant_id' => $pId, 'order_no' => 'SO-' . rand(1000,9999)], ['status' => 'In Progress', 'total_qty' => 100]);

            return [
                'sales_order_id' => $so->id,
                'site_id' => $site->id,
                'pump_vehicle_id' => $machine->id,
                'operator_id' => $operator->id,
                'mix_design_id' => $mix->id,
            ];
        });
    }
}
