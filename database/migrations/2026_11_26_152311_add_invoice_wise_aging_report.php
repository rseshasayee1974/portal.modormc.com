<?php
/*
 * Author: ragul-onemodo
 * Created: 2026-11-26 15:23:11 Asia/Calcutta (UTC+05:30)
 */

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        \App\Services\Reports\InstallReportPermissions::run();

        $permissionIds = \Illuminate\Support\Facades\DB::table('mm_permissions')
            ->whereIn('name', [
                \App\Services\Reports\ReportPermissions::name('customer_outstanding', 'VIEW'),
                \App\Services\Reports\ReportPermissions::name('customer_outstanding', 'EXPORT'),
                \App\Services\Reports\ReportPermissions::name('invoice_wise_aging', 'VIEW'),
                \App\Services\Reports\ReportPermissions::name('invoice_wise_aging', 'EXPORT'),
            ])->pluck('id', 'name');

        foreach (['VIEW', 'EXPORT'] as $action) {
            $sourceId = $permissionIds[\App\Services\Reports\ReportPermissions::name('customer_outstanding', $action)] ?? null;
            $targetId = $permissionIds[\App\Services\Reports\ReportPermissions::name('invoice_wise_aging', $action)] ?? null;
            if (!$sourceId || !$targetId) continue;

            foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) {
                foreach (\Illuminate\Support\Facades\DB::table($table)->where('permission_id', $sourceId)->get() as $grant) {
                    \Illuminate\Support\Facades\DB::table($table)->insertOrIgnore(
                        array_replace((array) $grant, ['permission_id' => $targetId])
                    );
                }
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('mm_menus')->where('alias', 'invoice-wise-aging')->delete();
        $names = array_map(
            fn ($action) => \App\Services\Reports\ReportPermissions::name('invoice_wise_aging', $action),
            \App\Services\Reports\ReportPermissions::ACTIONS
        );
        $ids = \Illuminate\Support\Facades\DB::table('mm_permissions')->whereIn('name', $names)->pluck('id');
        \Illuminate\Support\Facades\DB::table('mm_role_has_permissions')->whereIn('permission_id', $ids)->delete();
        \Illuminate\Support\Facades\DB::table('mm_model_has_permissions')->whereIn('permission_id', $ids)->delete();
        \Illuminate\Support\Facades\DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
