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
            \App\Models\Site::factory()->create([
                'plant_id' => $plant->id,
                'name' => $plant->name . ' Loading Bay',
                'type' => 'loading',
            ]);

            \App\Models\Site::factory()->create([
                'plant_id' => $plant->id,
                'name' => $plant->name . ' Unloading Zone',
                'type' => 'unloading',
            ]);
        }
    }
}
