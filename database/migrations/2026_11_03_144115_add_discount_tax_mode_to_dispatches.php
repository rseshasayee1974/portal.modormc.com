<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 14:41:15 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->string('discount_tax_mode', 16)->default('after_tax');
        });
    }

    public function down(): void
    {
        Schema::table('mm_dispatches', fn (Blueprint $table) => $table->dropColumn('discount_tax_mode'));
    }
};
