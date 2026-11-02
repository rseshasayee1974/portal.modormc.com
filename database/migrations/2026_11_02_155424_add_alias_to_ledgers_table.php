<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 15:54:24 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_ledgers', 'alias')) {
            Schema::table('mm_ledgers', function (Blueprint $table) {
                $table->string('alias', 50)->nullable()->after('title');
            });
        }

        // The old ledger Prefix input stored short codes in description.
        DB::table('mm_ledgers')->whereNull('alias')->whereNotNull('description')
            ->select('id', 'description')->orderBy('id')->chunkById(500, function ($ledgers) {
                foreach ($ledgers as $ledger) {
                    $alias = trim((string) $ledger->description, " \t\n\r\0\x0B/");
                    if (strlen($alias) <= 50 && preg_match('/^[A-Za-z0-9_-]+$/', $alias)) {
                        DB::table('mm_ledgers')->where('id', $ledger->id)->update(['alias' => $alias]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('mm_ledgers', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
