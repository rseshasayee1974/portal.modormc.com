<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/
namespace App\Services;

use Illuminate\Support\Facades\{DB, Schema};

class InstallCrmModule
{
    public static function run(): void
    {
        foreach (['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'ASSIGN', 'CONVERT'] as $action) {
            $name = 'CRM_LEAD.' . $action;
            self::upsert('mm_permissions', ['name' => $name, 'guard_name' => 'web'], [
                'module' => 'CRM_LEAD', 'description' => ucfirst(strtolower($action)) . ' CRM leads', 'is_system' => true, 'updated_at' => now(),
            ]);
            $id = DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            foreach (DB::table('mm_roles')->whereNull('deleted_at')->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR', 'SALES_MANAGER'])->pluck('id') as $role) {
                DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        }
        if (Schema::hasTable('mm_menus')) {
            $common = ['published' => 1, 'permission_name' => 'CRM_LEAD.VIEW', 'updated_at' => now()];
            self::upsert('mm_menus', ['alias' => 'crm'], array_merge($common, [
                'menutype' => 1, 'title' => 'CRM', 'link' => 'crm/leads', 'icon' => 'UserGroupIcon', 'parent_id' => 0, 'level' => 0, 'ordering' => 14,
            ]));
            $parent = DB::table('mm_menus')->where('alias', 'crm')->value('id');
            self::upsert('mm_menus', ['alias' => 'crm-leads'], array_merge($common, [
                'menutype' => 2, 'title' => 'Leads', 'link' => 'crm/leads', 'icon' => 'UserGroupIcon', 'parent_id' => $parent, 'level' => 1, 'ordering' => 1,
            ]));
        }
        self::clearCache();
    }

    public static function remove(): void
    {
        $ids = DB::table('mm_permissions')->where('module', 'CRM_LEAD')->pluck('id');
        foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) {
            DB::table($table)->whereIn('permission_id', $ids)->delete();
        }
        DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        DB::table('mm_menus')->whereIn('alias', ['crm', 'crm-leads'])->delete();
        self::clearCache();
    }

    private static function clearCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }

    private static function upsert(string $table, array $lookup, array $data): void
    {
        if (DB::table($table)->where($lookup)->exists()) {
            DB::table($table)->where($lookup)->update($data);
        } else {
            DB::table($table)->insert(array_merge($lookup, $data, ['created_at' => now()]));
        }
    }
}
