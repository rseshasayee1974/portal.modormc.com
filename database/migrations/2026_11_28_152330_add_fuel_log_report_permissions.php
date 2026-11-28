<?php
/*
Author: ragul-onemodo
Created: 2026-11-28 15:23:30 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \App\Services\Reports\InstallReportPermissions::run();
    }

    public function down(): void
    {
        $names = array_map(
            fn ($action) => \App\Services\Reports\ReportPermissions::name('fuel_log', $action),
            \App\Services\Reports\ReportPermissions::ACTIONS
        );
        $ids = \Illuminate\Support\Facades\DB::table('mm_permissions')->whereIn('name', $names)->pluck('id');
        \Illuminate\Support\Facades\DB::table('mm_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        \Illuminate\Support\Facades\DB::table('mm_model_has_permissions')->whereIn('permission_id', $ids)->delete();
        \Illuminate\Support\Facades\DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
