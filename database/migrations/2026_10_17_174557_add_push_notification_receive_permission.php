<?php

/*
Author: Cipher
Created: 2026-10-17 17:45:57 Asia/Calcutta (UTC+05:30)
*/

use App\Models\EntityUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mm_permissions')->updateOrInsert(['name' => 'PUSH_NOTIFICATION.RECEIVE', 'guard_name' => 'web'], [
            'module' => 'PUSH_NOTIFICATION', 'description' => 'Receive business push notifications', 'is_system' => true,
        ]);
        $permission = DB::table('mm_permissions')->where('name', 'PUSH_NOTIFICATION.RECEIVE')->where('guard_name', 'web')->value('id');
        // Preserve existing plant-wide delivery; administrators can turn it off per role.
        foreach (DB::table('mm_roles')->whereNull('deleted_at')->pluck('id') as $role) {
            DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        EntityUser::clearGlobalRolesCache();
    }

    public function down(): void
    {
        $ids = DB::table('mm_permissions')->where('name', 'PUSH_NOTIFICATION.RECEIVE')->where('guard_name', 'web')->pluck('id');
        DB::table('mm_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('mm_model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        EntityUser::clearGlobalRolesCache();
    }
};
