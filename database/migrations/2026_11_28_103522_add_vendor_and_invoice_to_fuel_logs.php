<?php
/*
Author: ragul-onemodo
Created: 2026-11-28 10:35:22 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_fuel_logs', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->constrained('mm_patrons')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('mm_invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_fuel_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
