<?php

namespace App\Services\Reports;

use App\Models\JournalEntryLine;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Patron;
use App\Services\PlantContextService;

class PatronReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];

        $query = JournalEntryLine::where('plant_id', $plantId);
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $openingBalance = (clone $query)
            ->whereHas('entry', fn($q) => $q->where('voucher_date', '<', $start))
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query
            ->with(['entry.lines.ledger', 'partner'])
            ->whereHas('entry', fn($q) => $q->whereBetween('voucher_date', [$start, $end]))
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function ($line) {
                $isDebit      = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id);
                $particulars  = $oppositeLines->count() == 1
                    ? ($isDebit ? 'To ' : 'By ') . ($oppositeLines->first()->ledger?->title ?? 'General Account')
                    : ($isDebit ? 'To ' : 'By ') . 'As per details';

                $patronNamePrefix = (!$line->partner_id) ? '[' . ($line->partner?->legal_name ?? 'N/A') . '] ' : '';

                return [
                    'date'         => $line->entry->voucher_date->toDateString(),
                    'due_date'     => $line->entry->due_date?->toDateString(),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no'   => $line->entry->voucher_number,
                    'narration'    => $patronNamePrefix . $particulars . ' (' . ($line->line_narration ?: $line->entry->narration) . ')',
                    'amount'       => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type'         => $isDebit ? 'Dr' : 'Cr',
                    'debit'        => (float)$line->debit_amount,
                    'credit'       => (float)$line->credit_amount,
                ];
            })->values();

        // Account summary (patron-specific only)
        $invoicedTaxTotal = $invoicedNonTaxTotal = $salesDiscount = 0.0;
        $purchased = $amountReceived = $amountPaid = 0.0;

        if ($patronId) {
            $invoices = Invoice::where('partner_id', $patronId)
                ->where('plant_id', $plantId)
                ->whereBetween('invoice_date', [$start, $end])
                ->get();

            foreach ($invoices as $inv) {
                if ($inv->tax_amount > 0) $invoicedTaxTotal    += $inv->total_amount;
                else                       $invoicedNonTaxTotal  += $inv->total_amount;
                $salesDiscount += ($inv->discount_amount ?? 0);
            }

            $purchased = PurchaseOrder::where('vendor_id', $patronId)
                ->where('plant_id', $plantId)
                ->whereBetween('date_order', [$start, $end])
                ->sum('amount_total');

            $jel = fn($type, $col) => JournalEntryLine::where('plant_id', $plantId)
                ->where('partner_id', $patronId)->where('partner_type', 'Patron')
                ->whereHas('entry', fn($q) => $q->where('voucher_type', $type)->whereBetween('voucher_date', [$start, $end]))
                ->sum($col);

            $amountReceived = $jel('RECEIPT', 'credit_amount');
            $amountPaid     = $jel('PAYMENT', 'debit_amount');
        }

        return [
            'opening_balance'  => (float)$openingBalance,
            'transactions'     => $transactions,
            'invoiced_tax'     => $invoicedTaxTotal,
            'invoiced_nontax'  => $invoicedNonTaxTotal,
            'sales_discount'   => $salesDiscount,
            'purchased'        => $purchased,
            'amount_received'  => $amountReceived,
            'amount_paid'      => $amountPaid,
            'credits'          => 0.00,
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id'])
            ? (Patron::find($params['patron_id'])?->legal_name ?? 'Patron')
            : 'All Patrons / Global Summary';
    }
}
