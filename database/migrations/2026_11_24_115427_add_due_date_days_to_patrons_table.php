<?php
/*
Author: ragul-onemodo
Created: 2026-11-24 11:54:27 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_patrons', 'due_date_days')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                $table->unsignedSmallInteger('due_date_days')->nullable()->after('credit_limit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mm_patrons', 'due_date_days')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                $table->dropColumn('due_date_days');
            });
        }
    }
};
