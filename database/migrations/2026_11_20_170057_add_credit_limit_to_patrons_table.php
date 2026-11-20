<?php
/*
Author: ragul-onemodo
Created: 2026-11-20 17:00:57 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_patrons', 'credit_limit')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                $table->decimal('credit_limit', 17, 2)->nullable()->after('credit_ledger_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mm_patrons', 'credit_limit')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                $table->dropColumn('credit_limit');
            });
        }
    }
};
