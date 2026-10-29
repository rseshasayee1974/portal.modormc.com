<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:43:56 Asia/Calcutta (UTC+05:30)
*/

use App\Services\InstallQualityTestingMenu;
use App\Services\Reports\InstallReportPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        InstallQualityTestingMenu::run();
        InstallReportPermissions::run();
        $parent = DB::table('mm_menus')->where('alias', 'report')->whereNull('deleted_at')->value('id');
        if ($parent) {
            DB::table('mm_menus')->updateOrInsert(['alias' => 'credit-debit-note-register-report'], [
                'menutype' => 2, 'title' => 'Credit Note & Debit Note Register',
                'link' => 'reports/report?type=credit_debit_note_register', 'icon' => 'DocumentChartBarIcon',
                'published' => 1, 'parent_id' => $parent, 'level' => 1, 'ordering' => 4,
                'permission_name' => 'REPORT_CREDIT_DEBIT_NOTE_REGISTER.VIEW',
                'updated_at' => now(), 'deleted_at' => null,
            ]);
        }
    }

    public function down(): void
    {
        // Keep menu customization and role grants made after installation.
    }
};
