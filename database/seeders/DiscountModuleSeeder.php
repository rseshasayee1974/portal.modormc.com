<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DiscountModuleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];
        foreach (['VIEW', 'CREATE', 'UPDATE', 'DELETE'] as $action) {
            $permissions[] = Permission::firstOrCreate(
                ['name' => "DISCOUNT.{$action}", 'guard_name' => 'web'],
                ['module' => 'DISCOUNT', 'description' => ucfirst(strtolower($action)) . ' discounts', 'is_system' => true]
            );
        }
        foreach (Role::whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR', 'ACCOUNTANT', 'FINANCE_MANAGER'])->get() as $role) {
            $role->givePermissionTo($permissions);
        }

        $finance = Menu::where('alias', 'finance')->first();
        if ($finance) {
            Menu::updateOrCreate(['alias' => 'discounts'], [
                'menutype' => 2, 'title' => 'Discounts', 'link' => 'finance/discounts',
                'icon' => 'ReceiptPercentIcon', 'published' => 1, 'parent_id' => $finance->id,
                'level' => 1, 'ordering' => 12, 'permission_name' => 'DISCOUNT.VIEW',
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
