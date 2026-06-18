<?php

namespace App\Ai\Tools;

use App\Models\Batch;
use App\Models\WorkOrder;
use Laravel\Ai\Contracts\Tool;
use Stringable;

/**
 * Tool: Search Orders & Batches
 *
 * Allows the internal assistant to search work orders and production batches.
 */
class SearchOrders implements Tool
{
    public function name(): Stringable|string
    {
        return 'search_orders';
    }

    public function description(): Stringable|string
    {
        return 'Search work orders and production batches by customer name, order number, date, or status. Returns recent batches and associated work order details.';
    }

    public function parameters(): array
    {
        return [
            'query' => [
                'type'        => 'string',
                'description' => 'Search term — customer name, order number, or batch number',
                'required'    => false,
            ],
            'status' => [
                'type'        => 'string',
                'description' => 'Filter by status: pending, in_progress, completed, cancelled',
                'required'    => false,
            ],
            'date_from' => [
                'type'        => 'string',
                'description' => 'Start date filter (YYYY-MM-DD)',
                'required'    => false,
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Maximum results (default: 10)',
                'required'    => false,
            ],
        ];
    }

    public function handle(
        string $query = '',
        string $status = '',
        string $date_from = '',
        int    $limit = 10
    ): string {
        $plantId = session('active_plant_id');

        $batchQuery = Batch::query()
            ->with(['workOrder.patron:id,name', 'workOrder.mixDesign:id,name'])
            ->select(['id', 'batch_no', 'status', 'batch_size', 'work_order_id', 'created_at'])
            ->orderByDesc('created_at')
            ->take(min($limit, 20));

        if ($plantId) {
            $batchQuery->where('plant_id', $plantId);
        }

        if (!empty($status)) {
            $batchQuery->where('status', $status);
        }

        if (!empty($date_from)) {
            $batchQuery->whereDate('created_at', '>=', $date_from);
        }

        if (!empty($query)) {
            $batchQuery->where(function ($q) use ($query) {
                $q->where('batch_no', 'LIKE', "%{$query}%")
                  ->orWhereHas('workOrder', fn ($wo) =>
                      $wo->where('order_no', 'LIKE', "%{$query}%")
                         ->orWhereHas('patron', fn ($p) => $p->where('name', 'LIKE', "%{$query}%"))
                  );
            });
        }

        $batches = $batchQuery->get();

        if ($batches->isEmpty()) {
            return "No orders/batches found" . (!empty($query) ? " matching '{$query}'" : '') . ".";
        }

        $result = "Found {$batches->count()} batch(es):\n\n";

        foreach ($batches as $batch) {
            $customer = $batch->workOrder?->patron?->name ?? 'Unknown Customer';
            $mixName  = $batch->workOrder?->mixDesign?->name ?? 'Unknown Mix';

            $result .= "• **Batch #{$batch->batch_no}** — Status: {$batch->status}\n";
            $result .= "  Customer: {$customer}\n";
            $result .= "  Mix Design: {$mixName}\n";
            $result .= "  Quantity: {$batch->batch_size} m³\n";
            $result .= "  Date: " . ($batch->created_at?->format('d M Y H:i') ?? 'N/A') . "\n\n";
        }

        return $result;
    }
}
