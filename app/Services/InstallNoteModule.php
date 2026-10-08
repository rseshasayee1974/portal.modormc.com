<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 18:30:31 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallNoteModule
{
    public static function run(): void
    {
        foreach (['VIEW', 'CREATE', 'UPDATE', 'DELETE'] as $action) {
            $name = 'CRDRNOTE.' . $action;
            if (DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->exists()) continue;
            $id = DB::table('mm_permissions')->insertGetId([
                'name' => $name, 'guard_name' => 'web', 'module' => 'CRDRNOTE',
                'description' => ucfirst(strtolower($action)) . ' credit and debit notes', 'is_system' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $legacy = DB::table('mm_permissions')->where('guard_name', 'web')
                ->whereIn('name', ['INVOICE.' . $action, 'BILLING.' . $action])->pluck('id');
            foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) {
                foreach (DB::table($table)->whereIn('permission_id', $legacy)->get() as $grant) {
                    DB::table($table)->insertOrIgnore(array_replace((array) $grant, ['permission_id' => $id]));
                }
            }
            foreach (DB::table('mm_roles')->whereNull('deleted_at')
                ->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'])->pluck('id') as $roleId) {
                DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id]);
            }
        }
        if (Schema::hasTable('mm_menus')) {
            $parent = DB::table('mm_menus')->where('link', 'finance/invoices')->whereNull('deleted_at')->value('parent_id');
            if ($parent && !DB::table('mm_menus')->where('alias', 'crdrnote')->exists()) {
                DB::table('mm_menus')->insert([
                    'menutype' => 2, 'title' => 'Credit / Debit Notes', 'alias' => 'crdrnote',
                    'link' => 'finance/crdrnote', 'icon' => 'DocumentTextIcon', 'published' => 1,
                    'parent_id' => $parent, 'level' => 1, 'ordering' => 6, 'permission_name' => 'CRDRNOTE.VIEW',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
}
