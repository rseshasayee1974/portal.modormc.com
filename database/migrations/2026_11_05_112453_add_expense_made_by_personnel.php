<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 11:24:53 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_expenses', function (Blueprint $table) {
            $table->foreignId('made_by_personnel_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('made_by_personnel_id'));
    }
};
