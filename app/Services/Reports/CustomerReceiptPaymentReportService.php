<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:10:45 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Reports;

use App\Models\Patron;
use App\Services\PlantContextService;
use Illuminate\Support\Facades\DB;

class CustomerReceiptPaymentReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start = substr($params['start'], 0, 10);
        $end = substr($params['end'], 0, 10);
        $customers = Patron::withoutGlobalScope('active_operational_status')
            ->where('plant_id', $plantId)->ofType('Customer')
            ->when($params['patron_id'] ?? null, fn ($query, $id) => $query->where('id', $id))
            ->pluck('legal_name', 'id');
        $ids = $customers->keys()->all();

        // A dual customer/vendor's supplier payments must not count as customer refunds.
        $payments = DB::table('mm_payments as payment')
            ->leftJoin('mm_ledgers as account', function ($join) use ($plantId) {
                $join->on('account.id', '=', 'payment.ledger_id')->where('account.plant_id', $plantId)->whereNull('account.deleted_at');
            })
            ->where('payment.plant_id', $plantId)->whereNull('payment.deleted_at')
            ->whereIn('payment.patron_id', $ids)
            ->whereRaw("LOWER(TRIM(COALESCE(payment.partner_type, ''))) IN ('customer', '')")
            ->whereRaw("LOWER(payment.status) IN ('paid', 'completed')")
            ->whereRaw("LOWER(payment.transaction_type) IN ('receipt', 'payment', 'rcpt', 'pmt')")
            ->whereBetween('payment.transaction_date', [$start, $end])
            ->select('payment.*', 'account.title as account_name')->get();
        $details = [];
        foreach ($payments as $payment) {
            $details[] = [
                'id' => 'payment_'.$payment->id, 'date' => substr($payment->transaction_date, 0, 10),
                'customer_id' => (int) $payment->patron_id, 'customer_name' => $customers[$payment->patron_id],
                'reference' => $payment->reference ?? '',
                'type' => in_array(strtolower($payment->transaction_type), ['receipt', 'rcpt']) ? 'Receipt' : 'Payment',
                'mode' => $payment->transaction_mode ?: 'Cash', 'account' => $payment->account_name ?? '—',
                'amount' => round((float) $payment->amount, 2),
            ];
        }

        // Include posted manual vouchers, excluding journals already represented by a payment record.
        $lines = DB::table('mm_journal_entry_lines as line')
            ->join('mm_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.plant_id', $plantId)->where('line.plant_id', $plantId)
            ->whereNull('entry.deleted_at')->whereNull('line.deleted_at')
            ->where(fn ($query) => $query->where('entry.is_deleted', 0)->orWhereNull('entry.is_deleted'))
            ->where(fn ($query) => $query->where('line.is_deleted', 0)->orWhereNull('line.is_deleted'))
            ->whereRaw("UPPER(entry.is_status) = 'POSTED'")
            ->whereRaw("UPPER(entry.voucher_type) IN ('RECEIPT', 'PAYMENT')")
            ->whereRaw("LOWER(COALESCE(entry.ref_module, '')) != 'payment'")
            ->whereRaw("LOWER(TRIM(COALESCE(line.partner_type, ''))) IN ('customer', 'patron', '')")
            ->whereIn('line.partner_id', $ids)->whereBetween('entry.voucher_date', [$start, $end])
            ->select('line.id', 'line.partner_id', 'line.debit_amount', 'line.credit_amount',
                'entry.voucher_date', 'entry.voucher_number', 'entry.voucher_type')->get();
        foreach ($lines as $line) {
            $receipt = strtoupper($line->voucher_type) === 'RECEIPT';
            $amount = round($receipt ? (float) $line->credit_amount - (float) $line->debit_amount
                : (float) $line->debit_amount - (float) $line->credit_amount, 2);
            if ($amount <= 0) continue;
            $details[] = [
                'id' => 'journal_line_'.$line->id, 'date' => substr($line->voucher_date, 0, 10),
                'customer_id' => (int) $line->partner_id, 'customer_name' => $customers[$line->partner_id],
                'reference' => $line->voucher_number ?? '', 'type' => $receipt ? 'Receipt' : 'Payment',
                'mode' => 'Journal Entry', 'account' => 'Journal Entry', 'amount' => $amount,
            ];
        }
        usort($details, fn ($a, $b) => [$a['date'], $a['reference'], $a['id']] <=> [$b['date'], $b['reference'], $b['id']]);
        $rows = [];
        foreach ($details as $detail) {
            $id = $detail['customer_id'];
            $rows[$id] ??= ['customer_id' => $id, 'customer_name' => $detail['customer_name'],
                'receipt_amt' => 0, 'payment_amt' => 0, 'net_amt' => 0];
            $field = $detail['type'] === 'Receipt' ? 'receipt_amt' : 'payment_amt';
            $rows[$id][$field] = round($rows[$id][$field] + $detail['amount'], 2);
            $rows[$id]['net_amt'] = round($rows[$id]['receipt_amt'] - $rows[$id]['payment_amt'], 2);
        }
        uasort($rows, fn ($a, $b) => strnatcasecmp($a['customer_name'], $b['customer_name']));
        $receipts = round(array_sum(array_column($rows, 'receipt_amt')), 2);
        $payments = round(array_sum(array_column($rows, 'payment_amt')), 2);
        return [
            'transactions' => array_values($rows), 'details' => $details,
            'total_receipt' => $receipts, 'total_payment' => $payments, 'net_received' => round($receipts - $payments, 2),
            'customer_count' => count($rows), 'voucher_count' => count($details),
            'headers' => ['Customer', 'Receipt Amount (₹)', 'Payment Amount (₹)', 'Net Received (₹)'],
            'fields' => ['customer_name', 'receipt_amt', 'payment_amt', 'net_amt'],
            'alignments' => ['left', 'right', 'right', 'right'],
            'totals' => ['receipt_amt' => $receipts, 'payment_amt' => $payments, 'net_amt' => round($receipts - $payments, 2)],
        ];
    }

    public function targetName(array $params): string
    {
        return 'Customer Receipt/Payment Amount';
    }
}
