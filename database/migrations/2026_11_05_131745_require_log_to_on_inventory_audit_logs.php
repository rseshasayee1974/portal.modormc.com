<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 13:17:45 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $column = collect(Schema::getColumns('mm_inventory_audit_logs'))->firstWhere('name', 'log_to');
        if ($column && !$column['nullable']) {
            return;
        }

        DB::table('mm_inventory_audit_logs')->whereNull('log_to')->update(['log_to' => '{}']);

        Schema::table('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->longText('log_to')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->longText('log_to')->nullable()->change();
        });
    }
};
