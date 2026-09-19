<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('mm_menus')) return;
        $journal = DB::table('mm_menus')->where('link', 'finance/journalentries')->first();
        if (!$journal || DB::table('mm_menus')->where('alias', 'opening-balances')->exists()) return;
        DB::table('mm_menus')->insert([
            'menutype' => 2, 'title' => 'Opening Balances', 'alias' => 'opening-balances',
            'link' => 'finance/opening-balances', 'icon' => 'DocumentChartBarIcon',
            'published' => 1, 'parent_id' => $journal->parent_id, 'level' => 1,
            'ordering' => 9, 'permission_name' => 'JOURNAL_ENTRY.VIEW',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('mm_menus')) {
            DB::table('mm_menus')->where('alias', 'opening-balances')->where('link', 'finance/opening-balances')->delete();
        }
    }
};
