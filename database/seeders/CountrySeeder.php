<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        // Minimal baseline so Country dropdown + validation work.
        Country::updateOrCreate(
            ['country_code' => 'IN'],
            ['country_name' => 'India', 'is_active' => 1]
        );
    }
}
