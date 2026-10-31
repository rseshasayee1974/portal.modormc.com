<?php

/*
Author: ragul-onemodo
Created: 2026-10-31 17:23:49 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_concrete_grade_items', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->default(0);
            $table->index(['concrete_grade_id', 'display_order'], 'grade_items_display_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('mm_concrete_grade_items', function (Blueprint $table) {
            $table->dropIndex('grade_items_display_order_index');
            $table->dropColumn('display_order');
        });
    }
};
