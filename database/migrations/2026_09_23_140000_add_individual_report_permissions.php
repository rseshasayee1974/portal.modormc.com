<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        \App\Services\Reports\InstallReportPermissions::run();
    }

    public function down(): void
    {
        $names = [];
        foreach (\App\Services\Reports\ReportPermissions::REPORTS as $id => $label) {
            foreach (\App\Services\Reports\ReportPermissions::ACTIONS as $action) {
                $names[] = \App\Services\Reports\ReportPermissions::name($id, $action);
            }
        }
        $ids = DB::table('mm_permissions')->whereIn('name', $names)->pluck('id');
        DB::table('mm_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('mm_model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        DB::table('mm_menus')->whereIn('permission_name', $names)->update(['permission_name' => 'REPORT.VIEW']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
};
