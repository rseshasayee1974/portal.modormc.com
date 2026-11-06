<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 16:07:52 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        \App\Services\InstallNotificationMenus::run();
    }

    public function down(): void
    {
        DB::table('mm_menus')->whereIn('alias', ['notificationemails', 'notificationwhatsapps'])
            ->update(['published' => 0, 'updated_at' => now()]);
    }
};
