<?php

namespace Database\Seeders;

use App\Models\ApiModule;
use Illuminate\Database\Seeder;

class ApiModuleSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'chat', 'price_per_1000_tokens' => 0.50, 'price_per_request' => 0.00],
            ['name' => 'image', 'price_per_1000_tokens' => 1.20, 'price_per_request' => 0.00],
            ['name' => 'search', 'price_per_1000_tokens' => 0.00, 'price_per_request' => 0.02],
        ];

        foreach ($rows as $row) {
            ApiModule::query()->updateOrCreate(['name' => $row['name']], $row);
        }
    }
}
