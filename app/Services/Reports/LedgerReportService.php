<?php

namespace App\Services\Reports;

use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Services\PlantContextService;

class LedgerReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $ledgerId = $params['id']       ?? null;
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];
        $voucherTypeFilter = strtoupper($params['voucher_type_filter'] ?? $params['voucher_filter'] ?? 'ALL');

        $isOpeningSql = "(mm_journal_entries.ref_module IN ('opening_balance', 'opening_balance_reversal') OR mm_journal_entries.voucher_type = 'OPENING')";
        // Opening journals are posted one day before the balance becomes effective.
        // Historical batches remain usable for dating legacy reversal journals.
        $dateSql = "CASE WHEN {$isOpeningSql} THEN COALESCE(
            (SELECT ob.cutover_date FROM mm_opening_balance_batches ob
             WHERE ob.id = mm_journal_entries.ref_id AND ob.plant_id = mm_journal_entries.plant_id),
            mm_journal_entries.voucher_date) ELSE mm_journal_entries.voucher_date END";

        // Auto-heal/sync any paid payments without accounting entries
        $unpostedPayments = \App\Models\Payment::where('plant_id', $plantId)
            ->where('status', 'paid')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('mm_journal_entries')
                    ->whereColumn('mm_journal_entries.ref_id', 'mm_payments.id')
                    ->where('mm_journal_entries.ref_module', 'payment')
                    ->whereNull('mm_journal_entries.deleted_at');
            })
            ->get();

        foreach ($unpostedPayments as $up) {
            try {
                $up->postToAccounting();
            } catch (\Throwable $e) {
                // Ignore if partner ledger mapping missing
            }
        }

        $query = JournalEntryLine::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->whereHas('entry', fn($q) => $q->where('plant_id', $plantId)->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')));

        if ($ledgerId) {
            $query->where('account_id', $ledgerId);
        }

        if ($patronId) {
            $query->where(function ($q) use ($patronId, $ledgerId, $plantId) {
                $q->where(function ($sq) use ($patronId) {
                    $sq->where('partner_id', $patronId)->where('partner_type', 'Patron');
                });

                // A patron statement must only sum the patron's side of each
                // journal. Include counterpart lines only for a selected ledger
                // (for example, a bank ledger filtered by customer).
                if ($ledgerId) {
                    $q->orWhere(function ($counterpart) use ($patronId, $plantId) {
                        $counterpart->where(fn ($untagged) => $untagged->whereNull('partner_id')->orWhereNull('partner_type')->orWhere('partner_type', '!=', 'Patron'))
                            ->whereHas('entry.lines', function ($elq) use ($patronId, $plantId) {
                                $elq->where('plant_id', $plantId)
                                    ->where('partner_id', $patronId)
                                    ->where('partner_type', 'Patron')
                                    ->whereNull('deleted_at')
                                    ->where(fn ($active) => $active->where('is_deleted', 0)->orWhereNull('is_deleted'));
                            });
                    });
                }
            });
        }

        // Carry the complete account balance forward even when period activity is filtered.
        $openingBalanceQuery = clone $query;

        // Voucher Type Filter
        if ($voucherTypeFilter && $voucherTypeFilter !== 'ALL') {
            if ($voucherTypeFilter === 'PAYMENT_RECEIPT' || $voucherTypeFilter === 'PAYMENT_AND_RECEIPT') {
                $query->whereHas('entry', fn($q) => $q->whereIn('voucher_type', ['PAYMENT', 'RECEIPT']));
            } else {
                $query->whereHas('entry', fn($q) => $q->whereIn('voucher_type', match ($voucherTypeFilter) {
                    'INVOICE', 'SALES' => ['INVOICE', 'SALES'],
                    'BILL', 'PURCHASE' => ['BILL', 'PURCHASE'],
                    default => [$voucherTypeFilter],
                }));
            }
        }

        $startDateStr = substr($start, 0, 10);
        $endDateStr   = substr($end, 0, 10);

        $openingBalances = $openingBalanceQuery
            ->whereHas('entry', function ($q) use ($startDateStr, $dateSql, $isOpeningSql) {
                $q->where(function ($dates) use ($startDateStr, $dateSql, $isOpeningSql) {
                    $dates->whereRaw("({$dateSql}) < ?", [$startDateStr])
                        ->orWhereRaw("({$isOpeningSql}) AND ({$dateSql}) = ?", [$startDateStr]);
                });
            })
            ->selectRaw('account_id, COALESCE(SUM(debit_amount), 0) - COALESCE(SUM(credit_amount), 0) as balance')
            ->groupBy('account_id')
            ->get();
        $openingBalance = (float) $openingBalances->sum('balance');
        $openingDebit = $openingBalances->sum(fn ($line) => max(0, (float) $line->balance));
        $openingCredit = $openingBalances->sum(fn ($line) => max(0, -(float) $line->balance));

        $transactions = $query
            ->with([
                'entry' => fn($q) => $q->select('mm_journal_entries.*')->selectRaw("{$dateSql} as report_date")->where('plant_id', $plantId)->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
                'entry.lines' => fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
                'entry.lines.ledger', 
                'entry.lines.partner', 
                'ledger',
                'partner'
            ])
            ->whereHas('entry', function ($q) use ($startDateStr, $endDateStr, $dateSql, $isOpeningSql) {
                $q->whereRaw("({$dateSql}) BETWEEN ? AND ?", [$startDateStr, $endDateStr])
                    ->whereRaw("NOT (COALESCE({$isOpeningSql}, 0) AND ({$dateSql}) = ?)", [$startDateStr]);
            })
            ->get()
            ->sortBy(fn ($line) => $line->entry->report_date.'_'.str_pad($line->entry->id, 20, '0', STR_PAD_LEFT).'_'.str_pad($line->id, 20, '0', STR_PAD_LEFT))
            ->map(function ($line) {
                $isDebit       = $line->debit_amount > 0;
                $vType         = strtoupper($line->entry->voucher_type ?? '');
                $refModule     = strtolower($line->entry->ref_module ?? '');
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id && empty($l->deleted_at) && empty($l->is_deleted));
                
                $oppositeParty = $oppositeLines->first(fn($ol) => !empty($ol->partner?->legal_name))?->partner?->legal_name;
                
                $coreOppLedger = $oppositeLines->first(function ($ol) {
                    $t = strtolower($ol->ledger?->title ?? '');
                    return str_contains($t, 'sales') || str_contains($t, 'purchase') || str_contains($t, 'bank') || str_contains($t, 'cash');
                })?->ledger?->title;

                $firstOppLedger = $oppositeLines->first()?->ledger?->title;
                
                if ($oppositeParty) {
                    $oppTitle = $oppositeParty;
                } elseif (in_array($vType, ['INVOICE', 'SALES'], true) || $refModule === 'invoice') {
                    $oppTitle = $coreOppLedger ?: 'Sales Account';
                } elseif (in_array($vType, ['BILL', 'PURCHASE'], true) || in_array($refModule, ['bill', 'purchase'], true)) {
                    $oppTitle = $coreOppLedger ?: 'Purchase Account';
                } else {
                    $oppTitle = $coreOppLedger ?: ($firstOppLedger ?: 'General Account');
                }

                $particulars = ($isDebit ? 'To ' : 'By ') . $oppTitle;

                $rawNarration = trim($line->line_narration ?: $line->entry->narration ?: '');
                $extraNarration = '';
                if ($rawNarration && !preg_match('/^(invoice|payment|receipt|bill)\s*#?[\w\/\-]+$/i', $rawNarration)) {
                    $extraNarration = " ({$rawNarration})";
                }

                return [
                    'id'           => $line->id,
                    'date'         => substr((string) $line->entry->report_date, 0, 10),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no'   => $line->entry->voucher_number,
                    'ref_module'   => $line->entry->ref_module,
                    'ref_id'       => $line->entry->ref_id,
                    'ledger_name'  => $line->ledger?->title ?? 'General Account',
                    'particulars'  => $particulars,
                    'narration'    => $particulars . $extraNarration,
                    'amount'       => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type'         => $isDebit ? 'Dr' : 'Cr',
                    'debit'        => (float)$line->debit_amount,
                    'credit'       => (float)$line->credit_amount,
                ];
            })->values();

        $balance = round((float) $openingBalance, 4);
        $transactions = $transactions->map(function ($transaction) use (&$balance) {
            $balance = round($balance + $transaction['debit'] - $transaction['credit'], 4);
            return [...$transaction, 'running_balance' => $balance];
        });

        return [
            'opening_balance' => round((float) $openingBalance, 4),
            'opening_debit' => round($openingDebit, 4),
            'opening_credit' => round($openingCredit, 4),
            'balance_scope' => $ledgerId ? 'ledger' : ($patronId ? 'patron' : 'all_ledgers'),
            'transactions' => $transactions,
            'total_debit' => round($transactions->sum('debit'), 4),
            'total_credit' => round($transactions->sum('credit'), 4),
            'closing_balance' => $balance,
        ];
    }

    public function targetName(array $params): string
    {
        if (isset($params['id'])) {
            return Ledger::whereNull('deleted_at')->find($params['id'])?->title ?? 'Ledger';
        }
        if (isset($params['patron_id'])) {
            return \App\Models\Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Partner Ledger';
        }
        return 'All Ledgers Consolidated';
    }
}
