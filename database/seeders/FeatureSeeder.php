<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['name' => 'Inventory', 'code' => 'INVENTORY_ACCESS', 'type' => 'boolean'],
            ['name' => 'HR', 'code' => 'HR_ACCESS', 'type' => 'boolean'],
            ['name' => 'Accounting', 'code' => 'ACCOUNTING_ACCESS', 'type' => 'boolean'],
            ['name' => 'API Access', 'code' => 'API_ACCESS', 'type' => 'boolean'],
            ['name' => 'Monthly Invoices', 'code' => 'MONTHLY_INVOICES', 'type' => 'metered'],
            ['name' => 'Max Users', 'code' => 'MAX_USERS', 'type' => 'tiered'],
        ];

        foreach ($features as $feature) {
            Feature::query()->updateOrCreate(
                ['code' => $feature['code']],
                array_merge($feature, ['is_active' => 1])
            );
        }
    }
}
