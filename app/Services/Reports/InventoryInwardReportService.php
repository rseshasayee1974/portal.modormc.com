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
        $start    = $params['start'];
        $end      = $params['end'];
        $patronId = $params['patron_id'] ?? null;
        $truckId  = $params['truck_id'] ?? null;

        $query = PurchaseOrderHistory::where('plant_id', $plantId)
            ->with(['order.vendor', 'product', 'uom', 'truck'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('received_date', [$start, $end])
                  ->orWhereBetween('created_at', [$start, $end]);
            });

        if ($patronId) {
            $query->whereHas('order', function ($q) use ($patronId) {
                $q->where('vendor_id', $patronId);
            });
        }

        if ($truckId) {
            $query->where('truck_id', $truckId);
        }

        $inwards = $query->orderBy('received_date', 'desc')->get();

        return [
            'transactions' => $inwards->map(fn($i) => [
                'date'         => $i->received_date ? \Carbon\Carbon::parse($i->received_date)->toDateString() : ($i->created_at ? $i->created_at->toDateString() : now()->toDateString()),
                'inward_no'    => $i->inward_no ?? ('INW-' . $i->id),
                'po_number'    => $i->order->po_number ?? 'N/A',
                'vendor_name'  => $i->order->vendor->legal_name ?? 'N/A',
                'product_name' => $i->product->title ?? 'N/A',
                'uom'          => $i->uom->unit_code ?? $i->uom->unit_name ?? 'N/A',
                'quantity'     => (float)$i->received_qty,
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
}
