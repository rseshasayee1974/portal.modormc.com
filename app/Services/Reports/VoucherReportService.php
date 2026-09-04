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
        $voucherType = strtoupper($params['voucher_type']); // 'PAYMENT' | 'RECEIPT'
        $patronId    = $params['patron_id'] ?? null;
        $start       = $params['start'];
        $end         = $params['end'];

        $query = JournalEntryLine::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->where('is_deleted', 0)->orWhereNull('is_deleted'))
            ->whereHas('entry', fn($q) => $q
                ->whereNull('deleted_at')
                ->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted'))
                ->where('voucher_type', $voucherType)
                ->whereBetween('voucher_date', [$start, $end])
            );

        if ($patronId) {
            $query->where(function ($q) use ($patronId) {
                $q->where('partner_id', $patronId)
                  ->orWhereHas('entry.lines', function ($sq) use ($patronId) {
                      $sq->where('partner_id', $patronId)
                         ->whereNull('deleted_at');
                  });
            });
        }

        $transactions = $query->with([
            'entry' => fn($q) => $q->whereNull('deleted_at')->where(fn($sq) => $sq->where('is_deleted', 0)->orWhereNull('is_deleted')),
            'ledger',
            'partner'
        ])->get()->map(function ($line) {
            $isDebit      = $line->debit_amount > 0;
            $partnerPrefix = $line->partner
                ? '[' . $line->partner->legal_name . '] '
                : '[' . ($line->ledger?->title ?? 'N/A') . '] ';

            return [
                'date'         => $line->entry->voucher_date->toDateString(),
                'voucher_type' => $line->entry->voucher_type,
                'voucher_no'   => $line->entry->voucher_number,
                'narration'    => $partnerPrefix . ($line->line_narration ?: $line->entry->narration),
                'amount'       => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                'type'         => $isDebit ? 'Dr' : 'Cr',
                'debit'        => (float)$line->debit_amount,
                'credit'       => (float)$line->credit_amount,
            ];
        });

        return ['opening_balance' => 0, 'transactions' => $transactions];
    }

    public function targetName(array $params): string
    {
        $label = strtoupper($params['voucher_type'] ?? '') === 'PAYMENT'
            ? 'Payment Vouchers'
            : 'Receipt Vouchers';

        return isset($params['patron_id'])
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Patron')
            : "All $label";
    }
}
