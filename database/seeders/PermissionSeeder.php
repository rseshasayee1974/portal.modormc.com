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

        // 2. DEFINE MODULES AND THEIR SPECIFIC VALID ACTIONS
        $moduleActions = [
            // Core System
            'USER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'ROLE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PERMISSION' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'ENTITY' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PLANT' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'MENU' => ['VIEW'],
            'DASHBOARD' => ['VIEW'],
            'SETTING' => ['VIEW', 'UPDATE'],
            
            // Financials
            'ACCOUNT' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'ACCOUNT_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'LEDGER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT', 'PDF'],
            'VOUCHER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'VOUCHER_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'JOURNAL_ENTRY' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'FISCAL_YEAR' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'EXPENSE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'EXPENSE_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PETTY_CASH' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'PAYMENT' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            
            // Commerce
            'PATRON' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'],
            'PRODUCT' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'],
            'PRODUCT_CATEGORY' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PRODUCT_UNIT' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'TAX' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PRICE_LIST' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'QUOTATION' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'SALES_ORDER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'WORK_ORDER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'PURCHASE_ORDER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'INVOICE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'PARTY_RATE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'],
            
            // Logistics & Ops
            'TRIP' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'EXPORT', 'PDF'],
            'MACHINE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'],
            'MACHINE_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PERSONNEL' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'],
            'DRIVER' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'FUEL_LOG' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'SITE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'MIX_DESIGN' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'],
            'CONCRETE_GRADE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'CONCRETE_QUALITY_TEST' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT', 'PDF'],
            
            // Master Data / Settings
            'ADDRESS_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'ENTITY_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'CONTACT_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'BANK_ACCOUNT_TYPE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'COUNTRY' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'CURRENCY' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'STATE_CODE' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'INVOICE_STATUS' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
            'PLAN' => ['VIEW', 'CREATE', 'UPDATE'],
            'SUBSCRIPTION_STATUS' => ['VIEW', 'CREATE', 'UPDATE'],
            'TERMS_CONDITION' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE']
        ];

        $allPermissionNames = [];

        foreach ($moduleActions as $module => $actions) {
            foreach ($actions as $action) {
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

        // Clean up any old/legacy permissions that are not part of the active list
        Permission::whereNotIn('name', $allPermissionNames)->delete();

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
                return Str::startsWith($p, ['TRIP', 'MACHINE', 'PERSONNEL', 'DRIVER', 'FUEL_LOG', 'SITE'])
                       || Str::contains($p, 'DASHBOARD.VIEW');
            });
            $transportRole->syncPermissions($transportPermissions);
        }

        // FLEET_MANAGER - Full Logistics access
        $fleetManagerRole = Role::where('code', 'FLEET_MANAGER')->first();
        if ($fleetManagerRole) {
            $fleetPermissions = array_filter($allPermissionNames, function($p) {
                return Str::startsWith($p, ['TRIP', 'MACHINE', 'MACHINE_TYPE', 'PERSONNEL', 'DRIVER', 'FUEL_LOG', 'SITE'])
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
