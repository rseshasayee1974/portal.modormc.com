<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AddressType;

class AddressTypeSeeder extends Seeder
{
    public function run(): void
    {
        AddressType::factory()->count(10)->create();
    }
}