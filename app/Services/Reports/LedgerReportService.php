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
            ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')));

        if ($ledgerId) {
            $query->where('account_id', $ledgerId);
        }

        if ($patronId) {
            $query->where(function ($q) use ($patronId) {
                $q->where(function ($sq) use ($patronId) {
                    $sq->where('partner_id', $patronId)->where('partner_type', 'Patron');
                })->orWhereHas('entry.lines', function ($elq) use ($patronId) {
                    $elq->where('partner_id', $patronId)
                        ->where('partner_type', 'Patron')
                        ->whereNull('deleted_at');
                });
            });
        }

        // Voucher Type Filter
        if ($voucherTypeFilter && $voucherTypeFilter !== 'ALL') {
            if ($voucherTypeFilter === 'PAYMENT_RECEIPT' || $voucherTypeFilter === 'PAYMENT_AND_RECEIPT') {
                $query->whereHas('entry', fn($q) => $q->whereIn('voucher_type', ['PAYMENT', 'RECEIPT']));
            } else {
                $query->whereHas('entry', fn($q) => $q->where('voucher_type', $voucherTypeFilter));
            }
        }

        $openingBalanceQuery = clone $query;
        $openingBalance = $openingBalanceQuery
            ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))->where('voucher_date', '<', substr($start, 0, 10)))
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query
            ->with([
                'entry' => fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
                'entry.lines' => fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
                'entry.lines.ledger', 
                'entry.lines.partner', 
                'ledger',
                'partner'
            ])
            ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))->whereBetween('voucher_date', [substr($start, 0, 10), substr($end, 0, 10)]))
            ->get()
            ->sortBy(fn($line) => ($line->entry->voucher_date ? $line->entry->voucher_date->format('Y-m-d') : '') . '_' . str_pad($line->entry->id, 8, '0', STR_PAD_LEFT))
            ->map(function ($line) use ($ledgerId) {
                $isDebit       = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id && empty($l->deleted_at) && empty($l->is_deleted));
                
                $oppositeParty = $oppositeLines->first(fn($ol) => !empty($ol->partner?->legal_name))?->partner?->legal_name;
                $oppositeLedger = $oppositeLines->first()?->ledger?->title;
                $oppTitle = $oppositeParty ?: ($oppositeLedger ?: 'General Account');

                $particulars = $oppositeLines->count() == 1
                    ? ($isDebit ? 'To ' : 'By ') . $oppTitle
                    : ($isDebit ? 'To ' : 'By ') . ($oppositeParty ?: 'As per details');

                $ledgerNamePrefix = (!$ledgerId) ? '[' . ($line->ledger?->title ?? 'N/A') . '] ' : '';
                $rawNarration = $line->line_narration ?: $line->entry->narration ?: '';

                return [
                    'date'         => $line->entry->voucher_date ? $line->entry->voucher_date->toDateString() : '',
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no'   => $line->entry->voucher_number,
                    'narration'    => $ledgerNamePrefix . $particulars . ($rawNarration ? " ({$rawNarration})" : ''),
                    'amount'       => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type'         => $isDebit ? 'Dr' : 'Cr',
                    'debit'        => (float)$line->debit_amount,
                    'credit'       => (float)$line->credit_amount,
                ];
            })->values();

        return ['opening_balance' => (float)$openingBalance, 'transactions' => $transactions];
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
