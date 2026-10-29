<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:11:18 Asia/Calcutta (UTC+05:30)
*/

use App\Services\Reports\InstallReportPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        InstallReportPermissions::run();
    }

    public function down(): void
    {
        // Preserve permissions and grants assigned after installation.
    }
};
