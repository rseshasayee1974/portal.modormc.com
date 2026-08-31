<?php

namespace Database\Seeders;

use App\Models\BatchSheetTemplate;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class Mci360BatchSheetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();

        foreach ($plants as $plant) {
            BatchSheetTemplate::updateOrCreate(
                [
                    'plant_id' => $plant->id,
                    'name' => 'MCI 360 Batch Report Ver 1.0',
                ],
                [
                    'source_type' => 'pdf',
                    'layout_fingerprint' => 'MCI 360 Control System Ver 1.0',
                    'keywords' => [
                        'MCI 360',
                        'Control System Ver 1.0',
                        'Docket / Batch Report / Autographic Record',
                        'Mass of Recipe targets in Kgs',
                        'Mass of Total Set weight in Kgs',
                        'Mass of Total Actual in Kgs',
                    ],
                    'field_mapping' => [
                        'batch_number' => 'Batch Number',
                        'batch_date' => 'Batch Date',
                        'plant_serial' => 'Plant Serial Number',
                        'batch_start_time' => 'Batch Start Time',
                        'batch_end_time' => 'Batch End Time',
                        'recipe_code' => 'Recipe Code',
                        'recipe_name' => 'Recipe Name',
                        'truck_number' => 'Truck Number',
                        'driver' => 'Truck Driver',
                        'batcher_name' => 'Batcher Name',
                        'customer' => 'Customer',
                        'site' => 'Site',
                        'order_number' => 'Order Number',
                        'batch_size' => 'Production Quantity',
                        'mixer_capacity' => 'Mixer Capacity',
                        'ordered_qty' => 'Ordered Quantity',
                        'production_qty' => 'Production Quantity',
                        'with_this_load' => 'With This Load',
                        'total_set_weight' => 'Mass of Total Set weight in Kgs',
                        'total_actual_weight' => 'Mass of Total Actual in Kgs',
                    ],
                    'is_active' => true,
                ]
            );
        }
    }
}
