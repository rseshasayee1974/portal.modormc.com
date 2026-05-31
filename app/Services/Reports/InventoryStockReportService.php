<?php

namespace App\Services\Reports;

use App\Models\Quantity;
use App\Services\PlantContextService;

class InventoryStockReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $stocks = Quantity::where('plant_id', $plantId)
            ->with(['product', 'uom'])
            ->whereBetween('date', [$start, $end])
            ->get();

        return [
            'transactions' => $stocks->map(fn($s) => [
                'date'         => $s->date->toDateString(),
                'product_name' => $s->product->title ?? 'N/A',
                'uom'          => $s->uom->name ?? 'N/A',
                'opening_qty'  => (float)$s->opening_quantity,
                'quantity'     => (float)$s->quantity,
                'status'       => $s->status ? 'Active' : 'Inactive',
            ])->values(),
            'total_quantity'  => (float)$stocks->sum('quantity'),
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Stock Level Inventory Report';
    }
}
