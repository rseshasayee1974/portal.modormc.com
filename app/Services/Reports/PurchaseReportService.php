<?php

namespace App\Services\Reports;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Patron;
use App\Services\PlantContextService;

class PurchaseReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];

        // 1. PO-wise transactions
        $basePoQuery = PurchaseOrder::with(['vendor'])
            ->where('plant_id', $plantId)
            ->whereNull('deleted_at');
        if ($patronId) {
            $basePoQuery->where('vendor_id', $patronId);
        }

        $poQuery = (clone $basePoQuery)->where(function ($q) use ($start, $end) {
            $q->whereBetween('date_order', [$start, $end])
              ->orWhereBetween('billed_date', [$start, $end])
              ->orWhereBetween('created_at', [$start, $end]);
        });

        $orders = $poQuery->orderBy('date_order', 'desc')->orderBy('po_number', 'desc')->get();

        // Fallback: If date range has no matches, load all active POs for the plant (similar to stock status)
        if ($orders->isEmpty()) {
            $orders = (clone $basePoQuery)->orderBy('date_order', 'desc')->orderBy('po_number', 'desc')->get();
        }

        $bills = $orders->map(fn($po) => [
            'date'           => $po->billed_date ? \Carbon\Carbon::parse($po->billed_date)->toDateString() : ($po->date_order ? \Carbon\Carbon::parse($po->date_order)->toDateString() : ($po->created_at ? $po->created_at->toDateString() : now()->toDateString())),
            'voucher_type'   => 'PURCHASE',
            'voucher_no'     => $po->bill_number ?: $po->po_number,
            'po_number'      => $po->po_number,
            'vendor_name'    => $po->vendor?->legal_name ?? 'N/A',
            'narration'      => '[' . ($po->vendor?->legal_name ?? 'Vendor') . '] Purchase Bill',
            'amount'         => (float)$po->amount_total,
            'amount_total'   => (float)$po->amount_total,
            'amount_untaxed' => (float)$po->amount_untaxed,
            'amount_tax'     => (float)$po->amount_tax,
            'type'           => 'Dr',
            'debit'          => (float)$po->amount_total,
            'credit'         => 0,
        ]);

        // 2. Product-wise consolidated
        $orderIds = $orders->pluck('id')->all();
        $itemQuery = PurchaseOrderItem::whereNull('deleted_at')
            ->whereIn('order_id', $orderIds)
            ->with(['product', 'uom']);

        $items = $itemQuery->get();

        $grouped = $items->groupBy('product_id')
            ->map(function ($items) {
                $first      = $items->first();
                $totalQty   = (float)$items->sum('product_quantity');
                $untaxed    = (float)$items->sum('price_subtotal');
                return [
                    'product_name'   => $first->product?->title ?? '',
                    'uom'            => $first->uom?->unit_name ?? $first->uom?->code ?? 'Unit',
                    'quantity'       => $totalQty,
                    'avg_rate'       => $totalQty > 0 ? $untaxed / $totalQty : 0.0,
                    'amount_untaxed' => $untaxed,
                    'amount_tax'     => (float)$items->sum('price_tax'),
                    'amount_total'   => (float)$items->sum('price_total'),
                ];
            })->values()->sortBy('product_name')->values();

        return [
            'opening_balance'       => 0,
            'transactions'          => $bills,
            'total_untaxed'         => (float)$orders->sum('amount_untaxed'),
            'total_tax'             => (float)$orders->sum('amount_tax'),
            'total_amount'          => (float)$orders->sum('amount_total'),
            'product_summary'       => $grouped,
            'total_quantity'        => (float)$grouped->sum('quantity'),
            'total_product_untaxed' => (float)$grouped->sum('amount_untaxed'),
            'total_product_tax'     => (float)$grouped->sum('amount_tax'),
            'total_product_amount'  => (float)$grouped->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id'])
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Vendor')
            : 'All Vendor Purchases';
    }
}
