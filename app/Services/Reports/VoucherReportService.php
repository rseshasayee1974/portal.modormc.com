<?php

namespace App\Services\Reports;

use App\Models\JournalEntryLine;
use App\Models\Patron;
use App\Services\PlantContextService;

/**
 * Handles both PAYMENT and RECEIPT voucher reports.
 * The voucher type ('PAYMENT' or 'RECEIPT') is passed via $params['voucher_type'].
 */
class VoucherReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId     = $this->ctx->requirePlantId();
        $voucherType = strtoupper($params['voucher_type'] ?? 'PAYMENT'); // 'PAYMENT' | 'RECEIPT'
        $typeShort   = strtolower($voucherType); // 'payment' | 'receipt'
        $patronId    = $params['patron_id'] ?? null;
        $ledgerId    = $params['id'] ?? null;
        $start       = $params['start'];
        $end         = $params['end'];

        $startDateOnly = substr($start, 0, 10);
        $endDateOnly   = substr($end, 0, 10);

        // 1. Query Payments & Receipts from mm_payments
        $paymentsQuery = \App\Models\Payment::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereIn('transaction_type', [$typeShort, ucfirst($typeShort)])
            ->whereBetween('transaction_date', [$startDateOnly, $endDateOnly])
            ->with(['ledger', 'patron']);

        if ($patronId) {
            $paymentsQuery->where('patron_id', $patronId);
        }

        if ($ledgerId) {
            $paymentsQuery->where('ledger_id', $ledgerId);
        }

        $payments = $paymentsQuery->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->get();

        $transactions = [];
        $totalAmount = 0;

        foreach ($payments as $p) {
            $amt = (float)$p->amount;
            $totalAmount += $amt;

            // Auto-heal/sync missing journal entry for paid transactions
            if ($p->status === 'paid') {
                $hasEntry = \App\Models\JournalEntry::where('ref_module', 'payment')
                    ->where('ref_id', $p->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$hasEntry) {
                    try {
                        $p->postToAccounting();
                    } catch (\Throwable $e) {
                        // ignore if partner ledger mapping not found
                    }
                }
            }

            $transactions[] = [
                'id'           => $p->id,
                'date'         => $p->transaction_date ? $p->transaction_date->format('Y-m-d') : '',
                'voucher_no'   => $p->reference ?? ($voucherType . '-' . $p->id),
                'voucher_type' => $voucherType,
                'party_name'   => $p->patron?->legal_name ?? 'Direct / Unspecified',
                'patron_id'    => $p->patron_id,
                'account_name' => $p->ledger?->title ?? 'Bank / Cash',
                'ledger_id'    => $p->ledger_id,
                'payment_mode' => $p->transaction_mode ?: 'Cash',
                'narration'    => $p->description ?: ($voucherType === 'PAYMENT' ? "Payment #{$p->reference}" : "Receipt #{$p->reference}"),
                'amount'       => $amt,
                'status'       => ucfirst($p->status ?: 'paid'),
                'debit'        => $voucherType === 'PAYMENT' ? $amt : 0,
                'credit'       => $voucherType === 'RECEIPT' ? $amt : 0,
                'type'         => $voucherType === 'PAYMENT' ? 'Dr' : 'Cr',
            ];
        }

        // 2. Include any standalone manual Journal Entries (non-payment module)
        $manualEntriesQuery = \App\Models\JournalEntry::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->where('voucher_type', $voucherType)
            ->where(fn($q) => $q->whereNull('ref_module')->orWhere('ref_module', '!=', 'payment'))
            ->whereBetween('voucher_date', [$startDateOnly, $endDateOnly])
            ->with(['lines.ledger', 'lines.partner']);

        if ($patronId) {
            $manualEntriesQuery->whereHas('lines', fn($q) => $q->where('partner_id', $patronId)->whereNull('deleted_at'));
        }

        if ($ledgerId) {
            $manualEntriesQuery->whereHas('lines', fn($q) => $q->where('account_id', $ledgerId)->whereNull('deleted_at'));
        }

        $manualEntries = $manualEntriesQuery->orderBy('voucher_date', 'desc')->orderBy('id', 'desc')->get();

        foreach ($manualEntries as $entry) {
            $partyLine = $entry->lines->first(fn($l) => !empty($l->partner_id)) ?: $entry->lines->first();
            $bankLine  = $entry->lines->first(fn($l) => empty($l->partner_id) && $l->id !== ($partyLine?->id)) ?: $entry->lines->last();

            $amt = (float)$entry->total_debit;
            $totalAmount += $amt;

            $transactions[] = [
                'id'           => 'je_' . $entry->id,
                'date'         => $entry->voucher_date ? $entry->voucher_date->format('Y-m-d') : '',
                'voucher_no'   => $entry->voucher_number,
                'voucher_type' => $voucherType,
                'party_name'   => $partyLine?->partner?->legal_name ?? $partyLine?->ledger?->title ?? 'Direct / General',
                'patron_id'    => $partyLine?->partner_id,
                'account_name' => $bankLine?->ledger?->title ?? 'Journal Account',
                'ledger_id'    => $bankLine?->account_id,
                'payment_mode' => 'Journal Entry',
                'narration'    => $entry->narration ?: ($partyLine?->line_narration ?: "Journal {$voucherType}"),
                'amount'       => $amt,
                'status'       => 'Posted',
                'debit'        => $voucherType === 'PAYMENT' ? $amt : 0,
                'credit'       => $voucherType === 'RECEIPT' ? $amt : 0,
                'type'         => $voucherType === 'PAYMENT' ? 'Dr' : 'Cr',
            ];
        }

        return [
            'voucher_type'    => $voucherType,
            'total_count'     => count($transactions),
            'total_amount'    => $totalAmount,
            'opening_balance' => 0,
            'transactions'    => $transactions,
        ];
    }

    public function targetName(array $params): string
    {
        $label = strtoupper($params['voucher_type'] ?? '') === 'PAYMENT'
            ? 'Payment Vouchers'
            : 'Receipt Vouchers';

        if (!empty($params['patron_id'])) {
            return Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Patron';
        }

        if (!empty($params['id'])) {
            return \App\Models\Ledger::whereNull('deleted_at')->find($params['id'])?->title ?? 'Account';
        }

        return "All $label";
    }
}
