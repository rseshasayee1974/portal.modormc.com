<?php

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
        // Preserve grants that administrators may have assigned after installation.
    }
};
