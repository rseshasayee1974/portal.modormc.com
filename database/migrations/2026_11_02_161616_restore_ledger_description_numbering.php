<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:16:16 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_ledgers', 'prefix')) return;

        // Keep existing descriptions; recover recently entered prefixes where description was blank.
        DB::table('mm_ledgers')->whereNotNull('prefix')->where('prefix', '!=', '')
            ->where(fn ($query) => $query->whereNull('description')->orWhereRaw("TRIM(description) = ''"))
            ->update(['description' => DB::raw('prefix')]);

        Schema::table('mm_ledgers', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('prefix');
        });
    }

    public function down(): void
    {
        // Retain ledger data when rolling back.
    }
};
