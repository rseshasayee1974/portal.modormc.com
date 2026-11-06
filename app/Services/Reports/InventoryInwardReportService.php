<?php

namespace App\Services\Reports;

use App\Models\PurchaseOrderHistory;
use App\Services\PlantContextService;

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
                'vendor',
                'order' => fn($q) => $q->whereNull('deleted_at'),
                'order.vendor' => fn($q) => $q->whereNull('deleted_at'),
                'product' => fn($q) => $q->whereNull('deleted_at'),
                'uom',
                'truck' => fn($q) => $q->whereNull('deleted_at')
            ])
            ->select('mm_purchase_order_history.*')
            ->selectRaw("$receiptTimestamp AS report_received_at")
            ->whereRaw("($receiptTimestamp) BETWEEN ? AND ?", [$start, $end]);

        if ($patronId) {
            $query->where(fn ($q) => $q->where('vendor_id', $patronId)
                ->orWhereHas('order', fn ($order) => $order->where('vendor_id', $patronId)));
        }

        if ($truckId) {
            $query->where('truck_id', $truckId);
        }
        if (!empty($params['product_id'])) {
            $query->where('product_id', $params['product_id']);
        }

        $inwards = $query->orderByDesc('report_received_at')->orderByDesc('id')->get();

        return [
            'transactions' => $inwards->map(fn($i) => [
                'date'         => \Carbon\Carbon::parse($i->report_received_at)->toDateTimeString(),
                'inward_no'    => $i->inward_no ?? ('INW-' . $i->id),
                'po_number'    => $i->order->po_number ?? 'Direct Stock In',
                'vendor_name'  => $i->order?->vendor?->legal_name ?? $i->vendor?->legal_name ?? 'N/A',
                'product_name' => $i->product->title ?? 'N/A',
                'uom'          => $i->uom->unit_code ?? $i->uom->unit_name ?? 'N/A',
                'quantity'     => trim((float) $i->received_qty . ' ' . ($i->uom?->unit_code ?? '')),
                'truck_no'     => $i->truck->registration ?? 'N/A',
                'truck_loaded' => (float)$i->truck_loaded,
                'truck_empty'  => (float)$i->truck_empty,
            ])->values(),
            'total_quantity'  => (float)$inwards->sum('received_qty'),
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Purchase Order Inward Report';
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
