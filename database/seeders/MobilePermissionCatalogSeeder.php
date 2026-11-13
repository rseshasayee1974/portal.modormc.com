<?php
/*
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Models\MobilePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MobilePermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'USER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'DASHBOARD' => ['VIEW'],
            'SALES_SUMMARY' => ['VIEW'],
            'SALES_DETAILS' => ['VIEW'],
            'CUSTOMER_DETAILS' => ['VIEW'],
            'MIX_DESIGN' => ['VIEW'],
            'DISPATCH' => ['VIEW'],
            'BATCHING_SCHEDULE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'STOCK' => ['VIEW'],
            'CRM_LEAD' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'ASSIGN', 'CONVERT', 'APPROVE'],
            'QUOTATION' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'CONVERT'],
            'CUSTOMER_PO' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'CONVERT'],
            'SALES_ORDER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE'],
            'ATTENDANCE' => ['VIEW', 'CREATE', 'UPDATE', 'PDF'],
            'PAYSLIP' => ['VIEW', 'PDF'],
            'PATRON' => ['VIEW'],
            'SITE' => ['VIEW'],
            'TAX' => ['VIEW'],
            'PERSONNEL' => ['VIEW'],
            'MARKETING' => ['VIEW'],
            'NOTIFICATION' => ['VIEW', 'UPDATE'],
        ];
        $names = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $name = $module.'.'.$action;
                $names[] = $name;
                MobilePermission::updateOrCreate(['name' => $name], [
                    'module' => $module, 'action' => $action,
                    'description' => Str::title(strtolower(str_replace('_', ' ', $action)).' '.strtolower(str_replace('_', ' ', $module))),
                    'is_system' => true,
                ]);
            }
        }
        MobilePermission::where('is_system', true)->whereNotIn('name', $names)->delete();
    }
}
