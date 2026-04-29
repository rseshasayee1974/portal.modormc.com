<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntityAddress;

class EntityAddressSeeder extends Seeder
{
    public function run(): void
    {
        EntityAddress::factory()->count(10)->create();
    }
}