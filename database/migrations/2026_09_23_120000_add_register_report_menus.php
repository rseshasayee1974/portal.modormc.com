<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_menus')) return;
        $parent = DB::table('mm_menus')->where('menutype', 1)->where('alias', 'report')->whereNull('deleted_at')->first();
        if (!$parent) return;
        foreach (['sales' => 'Sales Register', 'purchase' => 'Purchase Register'] as $kind => $title) {
            if (DB::table('mm_menus')->where('alias', $kind.'-register-report')->exists()) continue;
            DB::table('mm_menus')->insert([
                'menutype' => 2, 'title' => $title, 'alias' => $kind.'-register-report',
                'link' => 'reports/report?type='.$kind.'_register', 'icon' => 'DocumentChartBarIcon',
                'published' => 1, 'parent_id' => $parent->id, 'level' => 1,
                'ordering' => $kind === 'sales' ? 1 : 2, 'permission_name' => 'REPORT.VIEW',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('mm_menus')) return;
        foreach (['sales', 'purchase'] as $kind) {
            DB::table('mm_menus')->where('alias', $kind.'-register-report')
                ->where('link', 'reports/report?type='.$kind.'_register')->delete();
        }
    }
};
