<?php
/*
Author: ragul-onemodo
Created: 2026-11-13 15:48:30 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_products', function (Blueprint $table) {
            $table->decimal('moisture', 6, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mm_products', function (Blueprint $table) {
            $table->dropColumn('moisture');
        });
    }
};
