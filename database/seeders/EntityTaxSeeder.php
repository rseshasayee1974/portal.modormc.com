<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntityTax;

class EntityTaxSeeder extends Seeder
{
    public function run(): void
    {
        EntityTax::factory()->count(10)->create();
    }
}