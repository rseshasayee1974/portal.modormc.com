<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:13:46 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Finance;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountingReports
{
    public function accounts(int $plant): array
    {
        return DB::table('mm_ledgers as l')
            ->leftJoin('mm_accounting_accounts as m', fn ($j) => $j->on('m.ledger_id', '=', 'l.id')->on('m.plant_id', '=', 'l.plant_id'))
            ->leftJoin('mm_account_types as t', fn ($j) => $j->on('t.id', '=', 'l.account_type_id')->on('t.plant_id', '=', 'l.plant_id'))
            ->leftJoin('mm_accounts as a', 'a.id', '=', 't.account_id')
            ->where('l.plant_id', $plant)->whereNull('l.deleted_at')->orderBy('l.code')->orderBy('l.id')
            ->get(['l.id', 'l.code', 'l.title', 'l.account_type_id', 'l.status', 'm.id as classification_id', 'm.type', 'm.parent_id', 'm.tax_class', 'm.control', 'm.active', 'a.title as root_type'])
            ->map(function ($a) {
                $a->type ??= match (strtoupper(trim($a->root_type ?? ''))) {
                    'ASSET', 'ASSETS' => 'asset', 'LIABILITY', 'LIABILITIES' => 'liability',
                    'CAPITAL', 'EQUITY' => 'equity', 'REVENUE', 'INCOME' => 'income',
                    'EXPENSE', 'EXPENSES' => 'expense', default => 'unclassified',
                };

                return (array) $a;
            })->all();
    }

    private function lines(int $plant, string $to): Builder
    {
        return DB::table('mm_journal_entry_lines as l')->join('mm_journal_entries as j', function ($join) {
            $join->on('j.id', '=', 'l.journal_entry_id')->on('j.plant_id', '=', 'l.plant_id');
        })->where('j.plant_id', $plant)->where('l.plant_id', $plant)
            ->whereNull('j.deleted_at')->whereNull('l.deleted_at')->where('j.is_deleted', 0)->where('l.is_deleted', 0)
            ->whereRaw("UPPER(j.is_status) = 'POSTED'")->whereDate('j.voucher_date', '<=', $to);
    }

    public function summary(int $plant, string $from, string $to): array
    {
        $accounts = collect($this->accounts($plant))->keyBy('id');
        $balances = [];
        foreach ($accounts as $id => $a) {
            $balances[$id] = ['account_id' => $id, 'code' => $a['code'], 'title' => $a['title'], 'type' => $a['type'], 'control' => $a['control'], 'tax_class' => $a['tax_class'], 'opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0];
        }
        $warnings = [];
        foreach ($this->lines($plant, $to)->orderBy('l.id')->select('l.account_id', 'l.debit_amount', 'l.credit_amount', 'j.voucher_date')->cursor() as $l) {
            if (! isset($balances[$l->account_id])) {
                $balances[$l->account_id] = ['account_id' => $l->account_id, 'code' => '', 'title' => 'Missing/deleted ledger #'.$l->account_id, 'type' => 'unclassified', 'control' => null, 'tax_class' => null, 'opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0];
            }
            $b = &$balances[$l->account_id];
            $d = AccountingMoney::units((string) $l->debit_amount);
            $c = AccountingMoney::units((string) $l->credit_amount);
            if (substr($l->voucher_date, 0, 10) < $from) {
                $b['opening'] += $d - $c;
            } else {
                $b['debit'] += $d;
                $b['credit'] += $c;
            }
            $b['closing'] += $d - $c;
            unset($b);
        }
        $totals = ['opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0, 'trial_debit' => 0, 'trial_credit' => 0];
        $profit = ['income' => 0, 'expense' => 0, 'profit' => 0];
        $sheet = ['asset' => 0, 'liability' => 0, 'equity' => 0, 'retained_result' => 0, 'difference' => 0];
        $control = ['receivable' => 0, 'payable' => 0];
        $tax = [];
        foreach ($balances as &$b) {
            foreach (['opening', 'debit', 'credit', 'closing'] as $key) {
                $totals[$key] += $b[$key];
            }
            $totals['trial_debit'] += max($b['closing'], 0);
            $totals['trial_credit'] += max(-$b['closing'], 0);
            if ($b['type'] === 'income') {
                $profit['income'] += $b['credit'] - $b['debit'];
            }
            if ($b['type'] === 'expense') {
                $profit['expense'] += $b['debit'] - $b['credit'];
            }
            if (in_array($b['type'], ['income', 'expense'])) {
                $sheet['retained_result'] -= $b['closing'];
            }
            if ($b['type'] === 'asset') {
                $sheet['asset'] += $b['closing'];
            }
            if (in_array($b['type'], ['liability', 'equity'])) {
                $sheet[$b['type']] -= $b['closing'];
            }
            if ($b['type'] === 'unclassified' && ($b['closing'] || $b['debit'] || $b['credit'])) {
                $warnings[] = 'Classify ledger '.$b['title'].' before relying on financial statements.';
            }
            if ($b['control'] === 'receivable') {
                $control['receivable'] += $b['closing'];
            }
            if ($b['control'] === 'payable') {
                $control['payable'] -= $b['closing'];
            }
            if ($b['tax_class']) {
                $tax[$b['tax_class']] = ($tax[$b['tax_class']] ?? 0) + (str_starts_with($b['tax_class'], 'input_') ? $b['debit'] - $b['credit'] : $b['credit'] - $b['debit']);
            }
            foreach (['opening', 'debit', 'credit', 'closing'] as $key) {
                $b[$key] = AccountingMoney::decimal($b[$key]);
            }
        }
        unset($b);
        $profit['profit'] = $profit['income'] - $profit['expense'];
        $sheet['difference'] = $sheet['asset'] - $sheet['liability'] - $sheet['equity'] - $sheet['retained_result'];
        if ($totals['closing'] !== 0) {
            $warnings[] = 'Trial balance is out of balance; investigate persisted journal data.';
        }
        $format = fn ($a) => array_map(fn ($n) => AccountingMoney::decimal($n), $a);
        $reconciliation = null;
        if (Schema::hasTable('mm_bank_statement_lines')) {
            $q = DB::table('mm_bank_statement_lines')->where('plant_id', $plant)->whereNull('deleted_at')->whereBetween('transaction_date', [$from, $to]);
            $reconciliation = ['total' => (clone $q)->count(), 'matched' => (clone $q)->whereNotNull('reconciled_line_id')->count()];
        }

        $controls = $format($control);
        foreach (['receivable', 'payable'] as $role) {
            if (! $accounts->contains(fn ($a) => $a['control'] === $role)) {
                $controls[$role] = null;
            }
        }

        return ['plant_id' => $plant, 'from' => $from, 'to' => $to, 'accounts' => array_values($balances), 'totals' => $format($totals), 'profit_loss' => $format($profit), 'balance_sheet' => $format($sheet), 'controls' => $controls, 'gst' => $format($tax), 'warnings' => $warnings, 'reconciliation' => $reconciliation];
    }

    public function ledger(int $plant, int $account, string $from, string $to, string $reference = '', int $page = 1): array
    {
        abort_unless(DB::table('mm_ledgers')->where('plant_id', $plant)->where('id', $account)->exists(), 404);
        $balance = 0;
        $opening = 0;
        $rows = [];
        $count = 0;
        $offset = ($page - 1) * 50;
        $q = $this->lines($plant, $to)->where('l.account_id', $account)->orderBy('j.voucher_date')->orderBy('j.id')->orderBy('l.id')
            ->select('l.id', 'l.debit_amount', 'l.credit_amount', 'j.id as journal_id', 'j.voucher_number', 'j.voucher_date', 'j.ref_name', 'j.narration');
        foreach ($q->cursor() as $l) {
            $balance += AccountingMoney::units((string) $l->debit_amount) - AccountingMoney::units((string) $l->credit_amount);
            if (substr($l->voucher_date, 0, 10) < $from) {
                $opening = $balance;

                continue;
            }
            if ($reference !== '' && stripos(($l->ref_name ?? '').' '.$l->voucher_number.' '.$l->narration, $reference) === false) {
                continue;
            }
            if ($count >= $offset && count($rows) < 50) {
                $rows[] = (array) $l + ['running_balance' => AccountingMoney::decimal($balance)];
            }
            $count++;
        }

        return ['plant_id' => $plant, 'opening' => AccountingMoney::decimal($opening), 'closing' => AccountingMoney::decimal($balance), 'rows' => $rows, 'total' => $count, 'page' => $page, 'per_page' => 50];
    }

    public function journals(int $plant, string $from, string $to, string $search, int $page): array
    {
        $q = DB::table('mm_journal_entries')->where('plant_id', $plant)->whereNull('deleted_at')->where('is_deleted', 0)->whereBetween('voucher_date', [$from, $to]);
        $q->select('mm_journal_entries.*')->selectSub(DB::table('mm_accounting_journals')->select('journal_id')->where('plant_id', $plant)->where('sealed', true)->whereColumn('reversal_of', 'mm_journal_entries.id')->limit(1), 'reversed_by');
        if ($search !== '') {
            $q->where(fn ($q) => $q->where('voucher_number', 'like', '%'.$search.'%')->orWhere('narration', 'like', '%'.$search.'%')->orWhere('ref_name', 'like', '%'.$search.'%'));
        }

        return ['rows' => $q->orderByDesc('voucher_date')->orderByDesc('id')->paginate(50, ['*'], 'page', $page)];
    }
}
