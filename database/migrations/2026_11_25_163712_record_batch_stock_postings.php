<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 16:37:12 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_batches', fn (Blueprint $table) => $table->json('posted_stock')->nullable());
    }

    public function down(): void
    {
        Schema::table('mm_batches', fn (Blueprint $table) => $table->dropColumn('posted_stock'));
    }
};
