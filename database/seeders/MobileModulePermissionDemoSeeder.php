<?php
/*
Author: Cipher
Created: 2026-11-13 10:35:00 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Models\EntityUser;
use App\Models\MobileModulePermission;
use App\Models\Personnel;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class MobileModulePermissionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Mobile permissions demo data is restricted to local/testing environments.');
            return;
        }

        $managePermission = Permission::updateOrCreate(['name' => 'MOBILE_MODULE_PERMISSION.VIEW'], [
            'guard_name' => 'web', 'module' => 'MOBILE_MODULE_PERMISSION', 'is_system' => true,
            'description' => 'View mobile app module permissions',
        ]);
        foreach (['MOBILE_MODULE_PERMISSION.UPDATE', 'MOBILE_MODULE_PERMISSION.DELETE'] as $name) {
            Permission::updateOrCreate(['name' => $name], [
                'guard_name' => 'web', 'module' => 'MOBILE_MODULE_PERMISSION', 'is_system' => true,
                'description' => 'Manage mobile app module permissions',
            ]);
        }
        foreach (['VIEW', 'CREATE', 'UPDATE', 'DELETE'] as $action) {
            Permission::updateOrCreate(['name' => 'MOBILE_PERMISSION.'.$action], [
                'guard_name' => 'web', 'module' => 'MOBILE_PERMISSION', 'is_system' => true,
                'description' => 'Maintain the mobile permission catalog',
            ]);
        }
        foreach (['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'] as $roleCode) {
            $role = Role::where('code', $roleCode)->first();
            if ($role) $role->givePermissionTo(Permission::whereIn('module', ['MOBILE_MODULE_PERMISSION', 'MOBILE_PERMISSION'])->get());
        }
        if (Schema::hasTable('mm_menus')) {
            Menu::updateOrCreate(['alias' => 'mobile-app-permissions'], [
                'menutype' => 2, 'title' => 'Mobile App Permissions', 'link' => 'membership/mobile-app-permissions',
                'icon' => 'DevicePhoneMobileIcon', 'published' => 1, 'parent_id' => 8,
                'level' => 1, 'ordering' => 2, 'permission_name' => $managePermission->name,
            ]);
            Menu::updateOrCreate(['alias' => 'mobile-permission-catalog'], [
                'menutype' => 2, 'title' => 'Mobile Permission Catalog', 'link' => 'settings/mobile-permissions',
                'icon' => 'DevicePhoneMobileIcon', 'published' => 1, 'parent_id' => 2,
                'level' => 1, 'ordering' => 3, 'permission_name' => 'MOBILE_PERMISSION.VIEW',
            ]);
        }

        $this->call(MobilePermissionCatalogSeeder::class);

        $plant = Plant::where('is_active', 1)->orderBy('id')->first();
        if (!$plant) {
            $this->command?->warn('Create an active plant before seeding mobile permission demo data.');
            return;
        }

        $user = User::withTrashed()->updateOrCreate(['email' => 'mobile.demo@example.test'], [
            'username' => 'Mobile Demo', 'password' => Hash::make('MobileDemo123!'),
            'is_active' => true, 'is_otp_enabled' => true,
        ]);
        if ($user->trashed()) $user->restore();
        $role = Role::where('code', 'SALES_EXECUTIVE')->first() ?? Role::where('code', 'ADMINISTRATOR')->first();
        if ($role) {
            EntityUser::updateOrCreate(['user_id' => $user->id, 'entity_id' => $plant->entity_id, 'plant_id' => $plant->id], ['role_id' => $role->id]);
        }
        $personnel = Personnel::withTrashed()->updateOrCreate(['user_id' => $user->id, 'plant_id' => $plant->id], [
            'entity_id' => $plant->entity_id, 'first_name' => 'Mobile', 'last_name' => 'Demo', 'status' => 'active',
        ]);
        if ($personnel->trashed()) $personnel->restore();

        $moduleActions = [
            'CRM_LEAD' => ['VIEW', 'CREATE', 'UPDATE'], 'QUOTATION' => ['VIEW'],
            'CUSTOMER_PO' => ['VIEW'], 'SALES_ORDER' => ['VIEW'], 'DASHBOARD' => ['VIEW'],
            'SALES_SUMMARY' => ['VIEW'], 'SALES_DETAILS' => ['VIEW'], 'CUSTOMER_DETAILS' => ['VIEW'],
            'DISPATCH' => ['VIEW'], 'STOCK' => ['VIEW'], 'MIX_DESIGN' => ['VIEW'],
            'ATTENDANCE' => ['VIEW', 'CREATE', 'UPDATE', 'PDF'],
            'PAYSLIP' => ['VIEW', 'PDF'], 'PATRON' => ['VIEW'], 'SITE' => ['VIEW'],
            'TAX' => ['VIEW'], 'PERSONNEL' => ['VIEW'],
            'BATCHING_SCHEDULE' => ['VIEW'],
        ];
        MobileModulePermission::where('user_id', $user->id)->where('plant_id', $plant->id)->whereNotIn('module', array_keys($moduleActions))->delete();
        foreach ($moduleActions as $module => $actions) {
            MobileModulePermission::updateOrCreate(['user_id' => $user->id, 'plant_id' => $plant->id, 'module' => $module], [
                'actions' => $actions, 'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
        }

        $tokenValue = 'mobile-permissions-demo-token';
        $token = PersonalAccessToken::updateOrCreate([
            'tokenable_type' => $user->getMorphClass(), 'tokenable_id' => $user->id, 'name' => 'mobile-permissions-demo',
        ], ['token' => hash('sha256', $tokenValue), 'abilities' => ['*'], 'last_used_at' => null, 'expires_at' => null]);

        $this->command?->info('Mobile demo user: mobile.demo@example.test / MobileDemo123!');
        $this->command?->info('Demo bearer token: '.$token->id.'|'.$tokenValue);
        $this->command?->info("Demo plant: {$plant->id}; personnel: {$personnel->id}");
    }
}
