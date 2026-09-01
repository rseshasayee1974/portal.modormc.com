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

        $query = JournalEntryLine::where('plant_id', $plantId);
        if ($ledgerId) $query->where('account_id', $ledgerId);
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $openingBalance = (clone $query)
            ->whereHas('entry', fn($q) => $q->where('voucher_date', '<', $start))
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query
            ->with(['entry.lines.ledger', 'entry.lines.partner', 'ledger'])
            ->whereHas('entry', fn($q) => $q->whereBetween('voucher_date', [$start, $end]))
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function ($line) use ($ledgerId) {
                $isDebit      = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id);
                $particulars  = $oppositeLines->count() == 1
                    ? ($isDebit ? 'To ' : 'By ') . ($oppositeLines->first()->ledger?->title ?? $oppositeLines->first()->partner?->legal_name ?? 'Unknown')
                    : ($isDebit ? 'To ' : 'By ') . 'As per details';

                $ledgerNamePrefix = (!$ledgerId) ? '[' . ($line->ledger?->title ?? 'N/A') . '] ' : '';

                return [
                    'date'         => $line->entry->voucher_date->toDateString(),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no'   => $line->entry->voucher_number,
                    'narration'    => $ledgerNamePrefix . $particulars . ' (' . ($line->line_narration ?: $line->entry->narration) . ')',
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
        return isset($params['id'])
            ? (Ledger::whereNull('deleted_at')->find($params['id'])?->title ?? 'Ledger')
            : 'All Ledgers Consolidated';
    }
}
