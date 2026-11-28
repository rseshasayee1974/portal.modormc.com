<?php
/*
Author: ragul-onemodo
Created: 2026-11-28 10:43:11 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_fuel_logs', function (Blueprint $table) {
            $table->date('bill_date')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('mm_ledgers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_fuel_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('bill_date');
        });
    }
};
