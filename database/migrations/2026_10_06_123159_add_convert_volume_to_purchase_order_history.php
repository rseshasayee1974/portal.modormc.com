<?php

/*
Author: ragul-onemodo
Created: 2026-10-06 12:32:35 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->decimal('convert_volume', 17, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mm_purchase_order_history', fn (Blueprint $table) => $table->dropColumn('convert_volume'));
    }
};
