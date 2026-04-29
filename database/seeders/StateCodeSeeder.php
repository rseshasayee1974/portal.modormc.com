<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StateCode;

class StateCodeSeeder extends Seeder
{
    public function run(): void
    {
        StateCode::factory()->count(10)->create();
    }
}