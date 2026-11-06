<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 15:58:32 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_stock_exhaust', function (Blueprint $table) {
            $table->date('billed_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mm_stock_exhaust', function (Blueprint $table) {
            $table->date('billed_date')->nullable(false)->change();
        });
    }
};
