<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['module_name' => 'Invoice', 'display_value' => 'Sales Invoicing'],
            ['module_name' => 'Purchase', 'display_value' => 'Purchase Billing'],
            ['module_name' => 'Payment', 'display_value' => 'Payments'],
            ['module_name' => 'Receipt', 'display_value' => 'Receipts'],
            ['module_name' => 'Inventory', 'display_value' => 'Inventory Management'],
            ['module_name' => 'Patron', 'display_value' => 'Patron Ledgers'],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['module_name' => $module['module_name']],
                ['display_value' => $module['display_value'], 'is_active' => true]
            );
        }
    }
}
