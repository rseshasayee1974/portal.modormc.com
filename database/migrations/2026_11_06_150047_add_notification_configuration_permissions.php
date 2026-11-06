<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 15:00:47 Asia/Calcutta (UTC+05:30)
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            foreach (['NOTIFICATION_EMAIL' => 'Notification Emails', 'NOTIFICATION_WHATSAPP' => 'WhatsApp Notifications'] as $module => $label) {
                foreach (['VIEW', 'CREATE', 'UPDATE', 'DELETE'] as $action) {
                    $name = $module.'.'.$action;
                    if (DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->exists()) continue;
                    $id = DB::table('mm_permissions')->insertGetId([
                        'name' => $name, 'guard_name' => 'web', 'module' => $module,
                        'description' => ucfirst(strtolower($action)).' '.$label, 'is_system' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    // Preserve existing notification access when splitting the Settings permissions.
                    $legacy = DB::table('mm_permissions')->where('guard_name', 'web')
                        ->whereIn(DB::raw('UPPER(name)'), ['SETTING.'.$action, 'SETTINGS.'.$action])->pluck('id');
                    foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) {
                        foreach (DB::table($table)->whereIn('permission_id', $legacy)->get() as $grant) {
                            DB::table($table)->insertOrIgnore(array_replace((array) $grant, ['permission_id' => $id]));
                        }
                    }
                    foreach (DB::table('mm_roles')->whereNull('deleted_at')->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN'])->pluck('id') as $role) {
                        DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
                    }
                }
            }
            if (Schema::hasTable('mm_menus')) {
                foreach (['notificationemails' => 'NOTIFICATION_EMAIL.VIEW', 'notificationwhatsapps' => 'NOTIFICATION_WHATSAPP.VIEW'] as $path => $permission) {
                    DB::table('mm_menus')->whereIn('link', ['settings/'.$path, '/settings/'.$path])
                        ->update(['permission_name' => $permission]);
                }
            }
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }

    public function down(): void
    {
        $ids = DB::table('mm_permissions')->whereIn('module', ['NOTIFICATION_EMAIL', 'NOTIFICATION_WHATSAPP'])->pluck('id');
        foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) DB::table($table)->whereIn('permission_id', $ids)->delete();
        DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        if (Schema::hasTable('mm_menus')) DB::table('mm_menus')->whereIn('permission_name', ['NOTIFICATION_EMAIL.VIEW', 'NOTIFICATION_WHATSAPP.VIEW'])->update(['permission_name' => 'SETTING.VIEW']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
};
