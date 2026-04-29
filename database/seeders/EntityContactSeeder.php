<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntityContact;

class EntityContactSeeder extends Seeder
{
    public function run(): void
    {
        EntityContact::factory()->count(10)->create();
    }
}