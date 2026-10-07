<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 11:07:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Include deleted entries and preserve numbers, amounts, and accounting lines.
        DB::transaction(function () {
            DB::table('mm_journal_entries')->whereRaw("UPPER(TRIM(voucher_type)) IN ('SALES', 'INVOICE')")
                ->update(['voucher_type' => 'INVOICE']);
            DB::table('mm_journal_entries')->whereRaw("UPPER(TRIM(voucher_type)) IN ('PURCHASE', 'BILL')")
                ->update(['voucher_type' => 'BILL']);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('mm_journal_entries')->where('voucher_type', 'INVOICE')->update(['voucher_type' => 'SALES']);
            DB::table('mm_journal_entries')->where('voucher_type', 'BILL')->update(['voucher_type' => 'PURCHASE']);
        });
    }
};
