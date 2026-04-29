<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = \App\Models\Plant::all();
        
        foreach ($plants as $plant) {
            \App\Models\CustomSetting::updateOrCreate(
                [
                    'plant_id' => $plant->id,
                    'mm_module_name' => 'batching',
                ],
                [
                    'settings' => ['newweight' => 1]
                ]
            );
            
            \App\Models\CustomSetting::updateOrCreate(
                [
                    'plant_id' => $plant->id,
                    'mm_module_name' => 'orders',
                ],
                [
                    'settings' => ['manualweight' => 0]
                ]
            );
        }
    }
}
