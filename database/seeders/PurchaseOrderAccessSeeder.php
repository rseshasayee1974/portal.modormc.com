<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Menu;

class PurchaseOrderAccessSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Permissions
        $permissions = [
            'purchase-orders.menu',
            'purchase-orders.create',
            'purchase-orders.edit',
            'purchase-orders.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Assign to Super Administrator
        $role = Role::where('name', 'Super Administrator')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }

        // 3. Create Menu Entry (Sidebar under Inventory)
        // Clean up any old TopNav entry if it exists
        Menu::where('alias', 'purchase-orders')->where('menutype', 1)->delete();

        if (!Menu::where('link', 'purchase-orders')->exists()) {
            Menu::create([
                'menutype' => 2, // Sidebar
                'title' => 'Purchase Orders',
                'alias' => 'purchase-orders',
                'link' => 'purchase-orders',
                'icon' => 'BriefcaseIcon',
                'published' => true,
                'parent_id' => 5, // Under Inventory
                'level' => 1,
                'ordering' => 1,
                'permission_name' => 'purchase-orders.menu',
            ]);
        }
    }
}
