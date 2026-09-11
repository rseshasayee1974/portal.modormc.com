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
        $plantId  = $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];

        if ($patronId) {
            $custOutstandingService = app(CustomerOutstandingReportService::class);
            return $custOutstandingService->generateSinglePatronStatement((int)$patronId, $plantId, $start, $end, $params);
        }

        $query = JournalEntryLine::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')));

        if ($patronId) {
            $query->where('partner_id', $patronId)->where('partner_type', 'Patron');
        } else {
            $query->whereNotNull('partner_id')->where('partner_type', 'Patron');
        }

        $openingBalance = (clone $query)
            ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))->where('voucher_date', '<', $start))
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query
            ->with([
                'entry' => fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
                'entry.lines' => fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
                'entry.lines.ledger', 
                'partner'
            ])
            ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))->whereBetween('voucher_date', [$start, $end]))
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function ($line) use ($patronId) {
                $isDebit       = $line->debit_amount > 0;
                $vType         = strtoupper($line->entry->voucher_type ?? '');
                $refModule     = strtolower($line->entry->ref_module ?? '');
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id && empty($l->deleted_at) && empty($l->is_deleted));
                
                $oppositeParty = $oppositeLines->first(fn($ol) => !empty($ol->partner?->legal_name))?->partner?->legal_name;

                // Identify core operating / real account (Sales, Purchase, Bank, Cash, etc.)
                $coreLedgerLine = $oppositeLines->first(function ($ol) {
                    $t = strtolower($ol->ledger?->title ?? '');
                    return str_contains($t, 'sales') || str_contains($t, 'purchase') || str_contains($t, 'revenue') || str_contains($t, 'bank') || str_contains($t, 'cash');
                });

                $primaryOppositeLine = $coreLedgerLine ?: $oppositeLines->sortByDesc(fn($l) => (float)$l->debit_amount + (float)$l->credit_amount)->first();
                $realAccount = $primaryOppositeLine?->ledger?->title ?: 'General Account';

                $accountTitle = $oppositeParty ?: $realAccount;
                $particulars  = ($isDebit ? 'To ' : 'By ') . $accountTitle;

                $patronNamePrefix = (!$patronId && !empty($line->partner?->legal_name)) ? '[' . $line->partner->legal_name . '] ' : '';
                
                // Suppress redundant repetitive narrations like "Invoice #Inv/2627/21" while preserving custom remarks
                $rawNarration = trim($line->line_narration ?: $line->entry->narration ?: '');
                $extraNarration = '';
                if ($rawNarration && !preg_match('/^(invoice|payment|receipt|bill)\s*#?[\w\/\-]+$/i', $rawNarration)) {
                    $extraNarration = " ({$rawNarration})";
                }

                return [
                    'id'           => $line->id,
                    'date'         => $line->entry->voucher_date ? $line->entry->voucher_date->toDateString() : '',
                    'due_date'     => $line->entry->due_date?->toDateString(),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no'   => $line->entry->voucher_number,
                    'ref_module'   => $line->entry->ref_module,
                    'ref_id'       => $line->entry->ref_id,
                    'account_name' => $realAccount,
                    'particulars'  => $particulars,
                    'narration'    => $patronNamePrefix . $particulars . $extraNarration,
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
                ->whereNull('deleted_at')
                ->whereBetween('invoice_date', [$start, $end])
                ->get();

            foreach ($invoices as $inv) {
                if ($inv->tax_amount > 0) $invoicedTaxTotal    += $inv->total_amount;
                else                       $invoicedNonTaxTotal  += $inv->total_amount;
                $salesDiscount += ($inv->discount_amount ?? 0);
            }

            $purchased = PurchaseOrder::where('vendor_id', $patronId)
                ->where('plant_id', $plantId)
                ->whereNull('deleted_at')
                ->whereBetween('date_order', [$start, $end])
                ->sum('amount_total');

            $jel = fn($type, $col) => JournalEntryLine::where('plant_id', $plantId)
                ->whereNull('deleted_at')
                ->where(fn($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
                ->where('partner_id', $patronId)->where('partner_type', 'Patron')
                ->whereHas('entry', fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))->where('voucher_type', $type)->whereBetween('voucher_date', [$start, $end]))
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
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Patron')
            : 'All Patrons / Global Summary';
    }
}
