<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallReportPermissions
{
    public static function run(): void
    {
        DB::transaction(function () {
            foreach (ReportPermissions::REPORTS as $id => $label) {
                foreach (ReportPermissions::ACTIONS as $action) {
                    $name = ReportPermissions::name($id, $action);
                    // Do not re-grant permissions that administrators have subsequently revoked.
                    if (DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->exists()) continue;
                    $permissionId = DB::table('mm_permissions')->insertGetId([
                        'name' => $name, 'guard_name' => 'web', 'module' => 'REPORT_'.strtoupper($id),
                        'description' => ucfirst(strtolower($action)).' '.$label, 'is_system' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $legacyNames = match ($action) {
                        'VIEW' => ['REPORT.VIEW'],
                        'EXPORT' => ['REPORT.EXPORT'],
                        'SHARE' => ['REPORT.SHARE', 'REPORT.EXPORT'],
                        'SCHEDULE' => ['REPORT.CREATE', 'REPORT.DELETE', 'REPORT.SCHEDULE'],
                    };
                    $legacyIds = DB::table('mm_permissions')->where('guard_name', 'web')->whereIn('name', $legacyNames)->pluck('id');
                    foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) {
                        foreach (DB::table($table)->whereIn('permission_id', $legacyIds)->get() as $grant) {
                            DB::table($table)->insertOrIgnore(array_replace((array) $grant, ['permission_id' => $permissionId]));
                        }
                    }
                    foreach (DB::table('mm_roles')->whereNull('deleted_at')->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'])->pluck('id') as $roleId) {
                        DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                    }
                }
            }
            self::syncMenus();
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }

    public static function syncMenus(): void
    {
        if (!Schema::hasTable('mm_menus')) return;
        foreach (DB::table('mm_menus')->where('link', 'like', '%reports/%')->get() as $menu) {
            $path = trim(parse_url($menu->link, PHP_URL_PATH) ?? '', '/');
            parse_str(parse_url($menu->link, PHP_URL_QUERY) ?? '', $params);
            $type = $params['type'] ?? (str_ends_with($path, 'reports/bulk-documents') ? 'bulk_documents' : null);
            if (!$type) continue;
            $id = ReportPermissions::reportId($type, $params + ['register_view' => 'summary']);
            if (isset(ReportPermissions::REPORTS[$id])) {
                DB::table('mm_menus')->where('id', $menu->id)->update(['permission_name' => ReportPermissions::name($id, 'VIEW')]);
            }
        }
    }
}
