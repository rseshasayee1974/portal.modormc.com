<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:22:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->foreignId('sales_executive_id')->nullable()->default(null)
                ->constrained('mm_personnels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_patrons', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_executive_id'));
    }
};