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
        if (!$parent || DB::table('mm_menus')->where('alias', 'detailed-sales-register-report')->exists()) return;

        DB::table('mm_menus')->insert([
            'menutype' => 2, 'title' => 'Detailed Sales Register', 'alias' => 'detailed-sales-register-report',
            'link' => 'reports/report?type=sales_register&register_view=detail', 'icon' => 'DocumentChartBarIcon',
            'published' => 1, 'parent_id' => $parent->id, 'level' => 1,
            'ordering' => 3, 'permission_name' => 'REPORT.VIEW',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('mm_menus')) return;
        DB::table('mm_menus')->where('alias', 'detailed-sales-register-report')
            ->where('link', 'reports/report?type=sales_register&register_view=detail')->delete();
    }
};
