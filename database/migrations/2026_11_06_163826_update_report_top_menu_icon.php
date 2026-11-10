<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 16:38:26 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('mm_menus')->where('menutype', 1)->whereRaw('LOWER(alias) = ?', ['report'])
            ->update(['icon' => 'DocumentChartBarIcon', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('mm_menus')->where('menutype', 1)->whereRaw('LOWER(alias) = ?', ['report'])
            ->where('icon', 'DocumentChartBarIcon')
            ->update(['icon' => 'ChartBarIcon', 'updated_at' => now()]);
    }
};
