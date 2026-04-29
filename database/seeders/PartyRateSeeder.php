<?php

namespace Database\Seeders;

use App\Models\PartyRate;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Site;
use App\Models\ProductUnit;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PartyRateSeeder extends Seeder
{
    public function run(): void
    {
        $plant = Plant::first();
        if (!$plant) return;

        PartyRate::factory(10)->create([
            'plant_id' => $plant->id,
        ]);
    }
}
