<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 11:17:31 Asia/Calcutta (UTC+05:30)
*/

use App\Services\Reports\InstallReportPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void { InstallReportPermissions::run(); }
    public function down(): void { /* Preserve assigned report permissions. */ }
};
