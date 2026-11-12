<?php
/*
 * Author: Cipher
 * Created: 2026-11-12 15:17:56 Asia/Calcutta (UTC+05:30)
 */

namespace App\Services\Reports;

use App\Models\StockExhaustLine;
use App\Services\PlantContextService;
use Illuminate\Validation\ValidationException;

class StockOutReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start = $params['start'];
        $end = $params['end'];
        if ($end < $start) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
        }

        $query = StockExhaustLine::query()
            ->whereHas('stockExhaust', fn ($q) => $q->where('plant_id', $plantId)->whereNull('deleted_at'))
            ->whereNull('deleted_at')
            ->whereBetween('issue_date', [$start, $end])
            ->with(['stockExhaust.partner', 'product.unit', 'vehicle']);

        if (!empty($params['product_id'])) $query->where('product_id', $params['product_id']);
        if (!empty($params['patron_id'])) $query->whereHas('stockExhaust', fn ($q) => $q->where('partner_id', $params['patron_id']));
        if (!empty($params['truck_id'])) $query->where('vehicle_no', $params['truck_id']);

        $lines = $query->orderBy('issue_date')->orderBy('id')->get();
        $transactions = $lines->map(fn ($line) => [
            'date' => $line->issue_date?->toDateTimeString() ?? $line->stockExhaust?->issued_date?->toDateString() ?? '',
            'reference_number' => $line->stockExhaust?->reference_number ?? ('STO-' . $line->stock_id),
            'voucher_name' => $line->stockExhaust?->name ?? '',
            'bill_number' => $line->stockExhaust?->bill_number ?? '',
            'bill_reference' => $line->stockExhaust?->bill_number ?: ($line->stockExhaust?->reference_number ?? ('STO-' . $line->stock_id)),
            'billed_date' => $line->stockExhaust?->billed_date?->toDateString() ?? '',
            'partner_name' => $line->stockExhaust?->partner?->legal_name ?? '',
            'product_name' => $line->product?->title ?? 'N/A',
            'quantity' => (float) $line->quantity_issued,
            'uom' => $line->units ?: ($line->product?->unit?->unit_code ?? $line->product?->unit?->unit_name ?? ''),
            'no_items_issued' => (float) ($line->no_items_issued ?? 0),
            'unit_price' => (float) ($line->unit_price ?? 0),
            'discount_amount' => (float) ($line->discount_amount ?? 0),
            'untaxed_amount' => (float) ($line->untaxed_amount ?? 0),
            'tax_rate' => (float) ($line->tax_rate ?? 0),
            'tax_amount' => (float) ($line->tax_amount ?? 0),
            'total_amount' => (float) ($line->total_amount ?? 0),
            'issued_to' => $line->issued_to ?? '',
            'vehicle_no' => $line->vehicle?->registration ?? '',
            'issued_to_vehicle' => trim(implode(' / ', array_filter([
                $line->issued_to ?: $line->stockExhaust?->partner?->legal_name,
                $line->vehicle?->registration,
            ]))),
            'changed_km' => (float) ($line->changed_km ?? 0),
            'notes' => $line->notes ?? '',
        ])->values();

        return [
            'transactions' => $transactions,
            'total_quantity' => (float) $lines->sum('quantity_issued'),
            'total_untaxed_amount' => (float) $lines->sum('untaxed_amount'),
            'total_discount_amount' => (float) $lines->sum('discount_amount'),
            'total_tax_amount' => (float) $lines->sum('tax_amount'),
            'total_amount' => (float) $lines->sum('total_amount'),
            'opening_balance' => 0,
        ];
    }

    public function targetName(array $params): string { return 'Stock Out Report'; }
}
