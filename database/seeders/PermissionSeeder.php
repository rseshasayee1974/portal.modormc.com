<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Reset Cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. CLEANUP LEGACY PERMISSIONS
        // Delete permissions that don't match the new uppercase MODULE.ACTION pattern
        Permission::where('name', 'NOT REGEXP', '^[A-Z0_1-9]+\.[A-Z_]+$')->delete();
        
        // Also delete any existing valid permissions to ensure a clean sync if needed
        // (Optional: depending on if you want it strictly additive or restrictive)
        // For this refactor, we ensure only our generated permissions exist.
        // Permission::truncate();

        // 2. DEFINE MODULES AND ACTIONS
        $modules = [
            // Core System
            'USER', 'ROLE', 'PERMISSION', 'ENTITY', 'PLANT', 'MENU', 'DASHBOARD',
            
            // Financials
            'ACCOUNT', 'ACCOUNT_TYPE', 'LEDGER', 'VOUCHER', 'VOUCHER_TYPE', 'JOURNAL_ENTRY', 'FISCAL_YEAR',
            'EXPENSE', 'EXPENSE_TYPE', 'PETTY_CASH', 'PAYMENT',
            
            // Commerce
            'PATRON', 'PRODUCT', 'PRODUCT_CATEGORY', 'PRODUCT_UNIT', 'TAX', 'PRICE_LIST',
            'QUOTATION', 'SALES_ORDER', 'WORK_ORDER', 'PURCHASE_ORDER', 'INVOICE', 'PARTY_RATE',
            
            // Logistics & Ops
            'TRIP', 'MACHINE', 'MACHINE_TYPE', 'PERSONNEL', 'SITE', 'MIX_DESIGN', 'CONCRETE_GRADE',
            
            // Master Data / Settings
            'ADDRESS_TYPE', 'ENTITY_TYPE', 'CONTACT_TYPE', 'BANK_ACCOUNT_TYPE', 'COUNTRY', 'CURRENCY', 'STATE_CODE',
            'INVOICE_STATUS', 'PLAN', 'SUBSCRIPTION_STATUS', 'TERMS_CONDITION', 'SETTING'
        ];

        $actions = ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'IMPORT', 'PDF'];

        $allPermissionNames = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                // Filter out illogical combinations
                if ($module === 'DASHBOARD' && !in_array($action, ['VIEW', 'EXPORT'])) continue;
                if ($module === 'MENU' && !in_array($action, ['VIEW'])) continue;
                if (in_array($module, ['PLAN', 'SUBSCRIPTION_STATUS']) && $action === 'DELETE') continue;

                $name = "{$module}.{$action}";
                Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'guard_name'  => 'web',
                        'is_system'   => true,
                        'description' => Str::title($action) . ' ' . Str::title(str_replace('_', ' ', $module)),
                        'module'      => $module
                    ]
                );
                $allPermissionNames[] = $name;
            }
        }

        // 3. DEFINE ROLE PERMISSION MAPPING
        
        // SAAS_OWNER & PLATFORM_ADMIN get everything
        $fullAccessRoles = ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN'];
        foreach ($fullAccessRoles as $roleCode) {
            $role = Role::where('code', $roleCode)->first();
            if ($role) {
                $role->syncPermissions($allPermissionNames);
            }
        }

        // ADMINISTRATOR (Entity Level) - Everything except System configs (Roles/Permissions)
        $adminRole = Role::where('code', 'ADMINISTRATOR')->first();
        if ($adminRole) {
            $adminPermissions = array_filter($allPermissionNames, function($p) {
                return !Str::startsWith($p, ['PERMISSION', 'ROLE']);
            });
            $adminRole->syncPermissions($adminPermissions);
        }

        // ACCOUNTANT - Financials + Invoices + View basic masters
        $accountantRole = Role::where('code', 'ACCOUNTANT')->first();
        if ($accountantRole) {
            $accountantPermissions = array_filter($allPermissionNames, function($p) {
                return Str::startsWith($p, ['ACCOUNT', 'LEDGER', 'VOUCHER', 'INVOICE', 'PAYMENT', 'EXPENSE', 'PETTY_CASH', 'TAX', 'CURRENCY'])
                       || Str::endsWith($p, '.VIEW');
            });
            $accountantRole->syncPermissions($accountantPermissions);
        }

        // SALES_MANAGER - Sales modules full access + View basic masters
        $salesRole = Role::where('code', 'SALES_MANAGER')->first();
        if ($salesRole) {
            $salesPermissions = array_filter($allPermissionNames, function($p) {
                return Str::startsWith($p, ['QUOTATION', 'SALES_ORDER', 'INVOICE', 'PATRON', 'PRODUCT', 'SITE'])
                       || Str::endsWith($p, '.VIEW');
            });
            $salesRole->syncPermissions($salesPermissions);
        }

        // TRANSPORT_OPERATOR - Trip and Logistics focus
        $transportRole = Role::where('code', 'TRANSPORT_OPERATOR')->first();
        if ($transportRole) {
            $transportPermissions = array_filter($allPermissionNames, function($p) {
                return Str::startsWith($p, ['TRIP', 'MACHINE', 'PERSONNEL', 'SITE'])
                       || Str::contains($p, 'DASHBOARD.VIEW');
            });
            $transportRole->syncPermissions($transportPermissions);
        }

        // FLEET_MANAGER - Full Logistics access
        $fleetManagerRole = Role::where('code', 'FLEET_MANAGER')->first();
        if ($fleetManagerRole) {
            $fleetPermissions = array_filter($allPermissionNames, function($p) {
                return Str::startsWith($p, ['TRIP', 'MACHINE', 'MACHINE_TYPE', 'PERSONNEL', 'SITE'])
                       || Str::endsWith($p, '.VIEW');
            });
            $fleetManagerRole->syncPermissions($fleetPermissions);
        }

        // OPERATIONS_MANAGER - Broad access
        $opsManagerRole = Role::where('code', 'OPERATIONS_MANAGER')->first();
        if ($opsManagerRole) {
            $opsPermissions = array_filter($allPermissionNames, function($p) {
                return !Str::startsWith($p, ['PERMISSION', 'ROLE', 'FISCAL_YEAR', 'USER']);
            });
            $opsManagerRole->syncPermissions($opsPermissions);
        }

        // RE-FLUSH
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
