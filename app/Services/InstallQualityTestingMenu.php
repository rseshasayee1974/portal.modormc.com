<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:42:34 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InstallQualityTestingMenu
{
    public static function run(): void
    {
        DB::transaction(function () {
            $root = self::menu('quality', [
                'menutype' => 1, 'title' => 'Quality Testing & Report', 'link' => 'quality/dashboard',
                'icon' => 'BeakerIcon', 'published' => 1, 'parent_id' => 0, 'level' => 0,
                'ordering' => 13, 'permission_name' => 'QC.VIEW',
            ], 100);
            foreach ([
                ['quality-tests', 'Laboratory Tests', 'quality/tests', 'QC_TEST.VIEW', 'ClipboardDocumentCheckIcon', 2],
                ['quality-concrete-tests', 'Concrete Quality Tests', 'inventory/concrete-quality-tests', 'CONCRETE_QUALITY_TEST.VIEW', 'BeakerIcon', 3],
                ['quality-reports', 'QC Reports', 'quality/reports', 'QC_REPORT.VIEW', 'ChartBarIcon', 10],
            ] as [$alias, $title, $link, $permission, $icon, $order]) {
                self::menu($alias, ['menutype' => 2, 'title' => $title, 'link' => $link, 'icon' => $icon,
                    'published' => 1, 'parent_id' => $root, 'level' => 1, 'ordering' => $order, 'permission_name' => $permission]);
            }
        });
    }

    private static function menu(string $alias, array $data, ?int $preferredId = null): int
    {
        $existing = DB::table('mm_menus')->where('alias', $alias)->first();
        $data += ['updated_at' => now(), 'deleted_at' => null];
        if ($existing) {
            DB::table('mm_menus')->where('id', $existing->id)->update($data);
            return $existing->id;
        }
        if ($preferredId && !DB::table('mm_menus')->where('id', $preferredId)->exists()) $data['id'] = $preferredId;
        return DB::table('mm_menus')->insertGetId($data + ['alias' => $alias, 'created_at' => now()]);
    }
}
