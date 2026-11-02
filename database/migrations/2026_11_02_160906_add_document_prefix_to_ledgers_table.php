<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:09:06 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_ledgers', 'prefix')) {
            Schema::table('mm_ledgers', function (Blueprint $table) {
                $table->string('prefix', 50)->nullable()->after('title');
            });
        }

        $columns = ['id', 'description'];
        if (Schema::hasColumn('mm_ledgers', 'alias')) $columns[] = 'alias';
        DB::table('mm_ledgers')->whereNull('prefix')->select($columns)
            ->orderBy('id')->chunkById(500, function ($ledgers) {
                foreach ($ledgers as $ledger) {
                    // Preserve the earlier short-code input and legacy Prefix input.
                    $prefix = trim((string) (($ledger->alias ?? null) ?: $ledger->description));
                    if (strlen($prefix) <= 50 && preg_match('/^[A-Za-z0-9_\/{\}-]+$/', $prefix)) {
                        DB::table('mm_ledgers')->where('id', $ledger->id)->update(['prefix' => $prefix]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Prefix may predate this migration on an existing installation; retain its data.
    }
};
