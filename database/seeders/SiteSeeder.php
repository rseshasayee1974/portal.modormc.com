<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = \App\Models\Plant::all();

        if ($plants->isEmpty()) {
            $plants = \App\Models\Plant::factory()->count(2)->create();
        }

        foreach ($plants as $plant) {
            $loadingName = $plant->name . ' Loading Bay';
            if (!\App\Models\Site::where(['plant_id' => $plant->id, 'name' => $loadingName, 'type' => 'loading'])->exists()) {
                \App\Models\Site::factory()->create([
                    'plant_id' => $plant->id,
                    'name' => $loadingName,
                    'type' => 'loading',
                ]);
            }

            $unloadingName = $plant->name . ' Unloading Zone';
            if (!\App\Models\Site::where(['plant_id' => $plant->id, 'name' => $unloadingName, 'type' => 'unloading'])->exists()) {
                \App\Models\Site::factory()->create([
                    'plant_id' => $plant->id,
                    'name' => $unloadingName,
                    'type' => 'unloading',
                ]);
            }
        }
    }
}
