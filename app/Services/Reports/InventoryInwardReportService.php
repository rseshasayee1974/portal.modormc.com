<?php

namespace App\Services\Reports;

use App\Models\PurchaseOrderHistory;
use App\Services\PlantContextService;
use Illuminate\Support\Facades\Schema;

class InventoryInwardReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $start    = $this->boundary($params['start'] ?? null, false);
        $end      = $this->boundary($params['end'] ?? null, true);
        if ($end < $start) {
            throw \Illuminate\Validation\ValidationException::withMessages(['end_date' => 'The end date and time must be on or after the start date and time.']);
        }
        $patronId = $params['patron_id'] ?? null;
        $truckId  = $params['truck_id'] ?? null;
        $hasDirectVendor = Schema::hasColumn('mm_purchase_order_history', 'vendor_id');

        // Older receipts stored a date at midnight. Use the entry time only
        // when it belongs to that same receipt day.
        $receiptTimestamp = "CASE
            WHEN received_date IS NULL THEN created_at
            WHEN (TIME(received_date) = '00:00:00' OR TIME(received_date) IS NULL)
                AND DATE(received_date) = DATE(created_at) THEN created_at
            ELSE received_date
        END";

        $query = PurchaseOrderHistory::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with([
                'order' => fn($q) => $q->whereNull('deleted_at'),
                'order.vendor' => fn($q) => $q->whereNull('deleted_at'),
                'product' => fn($q) => $q->whereNull('deleted_at'),
                'uom',
                'conversionUom',
                'truck' => fn($q) => $q->whereNull('deleted_at')
            ])
            ->select('mm_purchase_order_history.*')
            ->selectRaw("$receiptTimestamp AS report_received_at")
            ->whereRaw("($receiptTimestamp) BETWEEN ? AND ?", [$start, $end]);

        if ($patronId) {
            $query->where(function ($q) use ($patronId, $hasDirectVendor) {
                $q->whereHas('order', fn ($order) => $order->where('vendor_id', $patronId));
                if ($hasDirectVendor) $q->orWhere('mm_purchase_order_history.vendor_id', $patronId);
            });
        }
        if ($hasDirectVendor) $query->with('vendor');

        if ($truckId) {
            $query->where('truck_id', $truckId);
        }
        if (!empty($params['product_id'])) {
            $query->where('product_id', $params['product_id']);
        }

        $inwards = $query->orderByDesc('report_received_at')->orderByDesc('id')->get();

        $totalQty = 0.0;
        $totalConvQty = 0.0;
        $grandTotalUnitPrice = 0.0;

        $transactions = $inwards->map(function ($i) use ($hasDirectVendor, &$totalQty, &$totalConvQty, &$grandTotalUnitPrice) {
            // Ensure values are treated as numbers for calculation
            $unitPrice = (float) ($i->unit_price ?? 0);
            $convQty = (float) ($i->conversion_quantity ?? 0);
            $recvQty = (float) ($i->received_qty ?? 0);
            
            // Calculate total based on whether conversion_quantity is greater than 0
            $totalUnitPrice = $convQty > 0 
                ? ($unitPrice * $convQty) 
                : ($unitPrice * $recvQty);

            $totalQty += $recvQty;
            $totalConvQty += $convQty;
            $grandTotalUnitPrice += $totalUnitPrice;

            return [
                'date'         => \Carbon\Carbon::parse($i->report_received_at)->toDateTimeString(),
                'inward_no'    => $i->inward_no ?? ('INW-' . $i->id),
                'po_number'    => $i->order->po_number ?? 'No linked PO',
                'vendor_name'  => $i->order?->vendor?->legal_name ?? ($hasDirectVendor ? $i->vendor?->legal_name : null) ?? 'N/A',
                'product_name' => $i->product->title ?? 'N/A',
                'uom'          => $i->uom->unit_code ?? $i->uom->unit_name ?? 'N/A',
                'quantity'     => trim($recvQty . ' ' . ($i->uom?->unit_code ?? '')),
                'conversion_quantity' => $convQty,
                'conversion_uom' => $i->conversionUom?->unit_code ?? $i->conversionUom?->unit_name ?? $i->uom?->unit_code ?? $i->uom?->unit_name ?? '',
                'conversion_quantity_display' => trim(number_format($convQty, 4, '.', '') . ' ' . ($i->conversionUom?->unit_code ?? $i->conversionUom?->unit_name ?? $i->uom?->unit_code ?? $i->uom?->unit_name ?? '')),
                'unit_price'       => number_format($unitPrice, 2, '.', ''),
                'total_unit_price' => number_format($totalUnitPrice, 2, '.', ''),
                'truck_no'     => $i->truck->registration ?? 'N/A',
                'truck_loaded' => (float)$i->truck_loaded,
                'truck_empty'  => (float)$i->truck_empty,
            ];
        })->values();

        return [
            'transactions'               => $transactions,
            'total_quantity'             => (float) $totalQty,
            'conversion_quantity_total'  => (float) $totalConvQty,
            'total_unit_price'           => (float) $grandTotalUnitPrice,
            'total_unit_prices'          => (float) $grandTotalUnitPrice,
            'opening_balance'            => 0,
        ];
    }

    public function targetName(array $params): string
    {
        return 'Purchase Order Inward Report';
    }

    public static function pdfColumns(array $data): array
    {
        return [
            'headers'    => ['Received Date', 'Inward No', 'PO No', 'Supplier Name', 'Product', 'Quantity', 'Conversion Quantity', 'Unit Price', 'Total Unit Price', 'Truck No'],
            'fields'     => ['date', 'inward_no', 'po_number', 'vendor_name', 'product_name', 'quantity', 'conversion_quantity_display', 'unit_price', 'total_unit_price', 'truck_no'],
            'alignments' => ['center', 'center', 'center', 'left', 'left', 'right', 'right', 'right', 'right', 'center'],
            'totals'     => [
                'quantity'                    => number_format((float) ($data['total_quantity'] ?? 0), 2, '.', ''),
                'conversion_quantity_display' => number_format((float) ($data['conversion_quantity_total'] ?? 0), 4, '.', ''),
                'total_unit_price'            => (float) ($data['total_unit_price'] ?? $data['total_unit_prices'] ?? 0),
            ]
        ];
    }

    private function boundary($value, bool $end): string
    {
        $date = $value ? \Carbon\Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'))
            : ($end ? now() : now()->startOfYear());
        if (!$value || preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $value))) {
            $date = $end ? $date->endOfDay() : $date->startOfDay();
        }
        return $date->toDateTimeString();
    }
}
