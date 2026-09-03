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

        $query = Quantity::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with(['product', 'uom']);

        // Check if there are records matching date range or created_at
        $stocks = (clone $query)->where(function ($q) use ($start, $end) {
            $q->whereBetween('date', [$start, $end])
              ->orWhereBetween('created_at', [$start, $end]);
        })->get();

        // Fallback: If date filtering yields empty set, return all active plant stock baselines
        if ($stocks->isEmpty()) {
            $stocks = $query->get();
        }

        return [
            'transactions' => $stocks->map(fn($s) => [
                'date'         => $s->date ? $s->date->toDateString() : ($s->created_at ? $s->created_at->toDateString() : now()->toDateString()),
                'product_name' => $s->product->title ?? 'N/A',
                'uom'          => $s->uom->unit_code ?? $s->uom->unit_name ?? 'N/A',
                'opening_qty'  => (float)$s->opening_quantity,
                'quantity'     => (float)$s->quantity,
                // 'status'       => $s->status ? 'Active' : 'Inactive',
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
