<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'SAAS_OWNER'          => ['level' => 100, 'description' => 'Full system owner with cross-entity visibility'],
            'PLATFORM_ADMIN'      => ['level' => 95,  'description' => 'Platform-level administrator'],
            'SUPER_ADMIN'         => ['level' => 90,  'description' => 'Super Administrator with full access'],
            'ADMINISTRATOR'       => ['level' => 80,  'description' => 'Entity-level administrator'],
            'OPERATIONS_MANAGER'  => ['level' => 70,  'description' => 'Head of operations'],
            'FINANCE_MANAGER'     => ['level' => 65,  'description' => 'Head of finance'],
            'ACCOUNTANT'          => ['level' => 60,  'description' => 'Handles accounting and books'],
            'INVENTORY_MANAGER'   => ['level' => 55,  'description' => 'Manages inventory and stock'],
            'WAREHOUSE_OPERATOR'  => ['level' => 50,  'description' => 'Handles warehouse activities'],
            'FLEET_MANAGER'       => ['level' => 45,  'description' => 'Manages transport and fleet'],
            'TRANSPORT_OPERATOR'  => ['level' => 40,  'description' => 'Handles transport logs'],
            'SALES_MANAGER'       => ['level' => 35,  'description' => 'Head of sales'],
            'SALES_EXECUTIVE'     => ['level' => 30,  'description' => 'Sales and business development'],
            'MARKETING_EXECUTIVE' => ['level' => 25,  'description' => 'Marketing and promotions'],
            'HR_MANAGER'          => ['level' => 20,  'description' => 'Human resources management'],
            'CRM_EXECUTIVE'       => ['level' => 15,  'description' => 'Customer relationship management'],
            'REPORT_ANALYST'      => ['level' => 10,  'description' => 'Views and generates reports'],
            'OFFICE_ADMIN'        => ['level' => 5,   'description' => 'General office administration'],
            'OPERATOR'            => ['level' => 1,   'description' => 'Basic system operator'],
        ];

        foreach ($roles as $code => $meta) {
            Role::updateOrCreate(
                ['code' => $code],
                [
                    'name'        => Str::title(str_replace('_', ' ', $code)),
                    'level'       => $meta['level'],
                    'description' => $meta['description'],
                    'guard_name'  => 'web',
                    'is_system'   => true,
                    'status'      => 'active'
                ]
            );
        }
    }
}