<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:31:30 Asia/Calcutta (UTC+05:30)
*/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountingPreflight extends Command
{
    protected $signature = 'accounting:preflight';

    protected $description = 'Read-only accounting storage, duplicate-code, and migration readiness checks';

    public function handle(): int
    {
        $failed = false;
        foreach (['mm_plants', 'mm_accounts', 'mm_account_types', 'mm_ledgers', 'mm_journal_entries', 'mm_journal_entry_lines', 'mm_invoices', 'mm_invoice_items', 'mm_order_taxes', 'mm_payments', 'mm_payment_transactions', 'mm_opening_balance_batches', 'mm_bank_statement_lines'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn($table.': absent; verify the corresponding existing module');

                continue;
            }
            if (DB::getDriverName() === 'mysql') {
                $engine = DB::table('information_schema.tables')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->value('ENGINE');
                $this->line($table.': '.$engine);
                if (strtoupper((string) $engine) !== 'INNODB') {
                    $failed = true;
                    $this->error('Requires reviewed InnoDB conversion: '.$table);
                }
            }
        }
        if (Schema::hasTable('mm_ledgers')) {
            $duplicate = DB::table('mm_ledgers')->whereNotNull('code')->groupBy('plant_id', 'code')->havingRaw('COUNT(*) > 1')->get(['plant_id', 'code']);
            $this->line('Duplicate plant/code groups: '.$duplicate->count());
            $failed = $failed || $duplicate->isNotEmpty();
        }
        if (Schema::hasTable('mm_journal_entries') && Schema::hasTable('mm_journal_entry_lines')) {
            $invalid = DB::table('mm_journal_entries as j')->leftJoin('mm_journal_entry_lines as l', function ($join) {
                $join->on('l.journal_entry_id', '=', 'j.id')->whereNull('l.deleted_at')->where('l.is_deleted', 0);
            })->whereRaw("UPPER(j.is_status) = 'POSTED'")->whereNull('j.deleted_at')->where('j.is_deleted', 0)
                ->select('j.id')->groupBy('j.id', 'j.total_debit', 'j.total_credit')
                ->havingRaw('COUNT(l.id) < 2 OR j.total_debit <= 0 OR j.total_debit != j.total_credit OR j.total_debit != COALESCE(SUM(l.debit_amount),0) OR j.total_credit != COALESCE(SUM(l.credit_amount),0)');
            $invalidCount = DB::query()->fromSub($invalid, 'invalid_journals')->count();
            $this->line('Posted journals needing header/line balance review: '.$invalidCount);
            $failed = $failed || $invalidCount > 0;
        }
        foreach (['mm_accounting_accounts', 'mm_accounting_periods', 'mm_accounting_journals', 'mm_accounting_events', 'mm_accounting_tally', 'mm_accounting_settings', 'mm_gst_periods'] as $table) {
            $present = Schema::hasTable($table);
            $this->line($table.': '.($present ? 'present' : 'migration pending'));
            $failed = $failed || ! $present;
        }
        $this->line('Tally listener: '.(config('gst_compliance.tally_url') ? 'configured; live acceptance still required' : 'not configured'));
        $this->line('No data or schema was changed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
