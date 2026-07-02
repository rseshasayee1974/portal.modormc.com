<?php

namespace App\Services\Reports;

use App\Models\PurchaseOrderHistory;
use App\Services\PlantContextService;

class InventoryInwardReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $inwards = PurchaseOrderHistory::where('plant_id', $plantId)
            ->with(['order.vendor', 'product', 'uom', 'truck'])
            ->whereBetween('received_date', [$start, $end])
            ->get();

        return [
            'transactions' => $inwards->map(fn($i) => [
                'date'         => $i->received_date,
                'inward_no'    => $i->inward_no,
                'po_number'    => $i->order->po_number ?? 'N/A',
                'vendor_name'  => $i->order->vendor->legal_name ?? 'N/A',
                'product_name' => $i->product->title ?? 'N/A',
                'uom'          => $i->uom->unit_code ?? 'N/A',
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
