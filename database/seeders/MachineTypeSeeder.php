<?php

namespace Database\Seeders;

use App\Models\MachineType;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class MachineTypeSeeder extends Seeder
{
    public function run(): void
    {
        $plant = Plant::first();
        if (!$plant) return;

        $types = ['Dump Truck', 'Excavator', 'Loader', 'Grader', 'Compactor'];
        foreach ($types as $type) {
            MachineType::firstOrCreate([
                'plant_id' => $plant->id,
                'name' => $type,
            ], []);
        }
    }
}
