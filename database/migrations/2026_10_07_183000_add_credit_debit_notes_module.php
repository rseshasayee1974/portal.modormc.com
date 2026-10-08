<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 18:30:31 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        \App\Services\InstallNoteModule::run();
    }

    public function down(): void
    {
        DB::table('mm_menus')->where('alias', 'crdrnote')->where('link', 'finance/crdrnote')->delete();
        $ids = DB::table('mm_permissions')->whereIn('name', ['CRDRNOTE.VIEW', 'CRDRNOTE.CREATE', 'CRDRNOTE.UPDATE'])->pluck('id');
        DB::table('mm_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('mm_model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
};
