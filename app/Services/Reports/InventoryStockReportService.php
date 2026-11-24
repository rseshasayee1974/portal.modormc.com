<?php

namespace App\Services\Reports;

use App\Models\Batch;
use App\Services\PlantContextService;
use Illuminate\Support\Facades\DB;

class InventoryStockReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public static function columns(): array
    {
        return [
            'headers' => ['Product', 'UOM', 'Opening Qty', 'Purchase Received', 'Issued Qty', 'Batch Consumption', 'Period Closing (Recorded Movements)', 'Net Movements After Period', 'Unexplained Balance Difference', 'Calculated Closing Qty (Today)', 'Current Stock'],
            'fields' => ['product_name', 'uom', 'opening_qty', 'purchase_qty', 'issued_qty', 'consumption_qty', 'period_closing', 'later_qty', 'balance_difference', 'closing_qty', 'quantity'],
            'alignments' => ['left', 'center', 'right', 'right', 'right', 'right', 'right', 'right', 'right', 'right', 'right'],
            'totals' => [],
        ];
    }

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start = $params['start'];
        $end = $params['end'];
        if (strlen($end) === 10) $end .= ' 23:59:59';

        $balances = DB::table('mm_quantity')->where('plant_id', $plantId)->whereNull('deleted_at')
            ->selectRaw('product_id, uom_id, CASE WHEN COALESCE(created_at, date) <= ? THEN opening_quantity ELSE 0 END AS baseline,
                quantity AS current_qty, NULL AS moved_at, 0 AS purchased, 0 AS issued, 0 AS consumed', [$end]);

        $receipts = DB::table('mm_purchase_order_history')->where('plant_id', $plantId)->whereNull('deleted_at')
            ->selectRaw('product_id, uom_id, 0, 0, COALESCE(received_date, created_at), received_qty, 0, 0');
        $issues = DB::table('mm_stock_exhaust_lines as l')->join('mm_stock_exhaust as s', 's.id', '=', 'l.stock_id')
            ->where('s.plant_id', $plantId)->whereNull('s.deleted_at')->whereNull('l.deleted_at')
            ->selectRaw('l.product_id, l.units, 0, 0, COALESCE(l.issue_date, s.issued_date), 0, l.quantity_issued, 0');
        $consumption = DB::table('mm_batch_materials as m')->join('mm_batches as b', 'b.id', '=', 'm.batch_id')
            ->where('b.plant_id', $plantId)->whereNull('b.deleted_at')->whereNull('m.deleted_at')
            ->whereIn('b.status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])->where('m.actual_qty', '>', 0)
            ->selectRaw('m.product_id, m.uom_id, 0, 0, COALESCE(b.start_time, b.created_at), 0, 0, m.actual_qty');

        $entries = $balances->unionAll($receipts)->unionAll($issues)->unionAll($consumption);
        $rows = DB::query()->fromSub($entries, 'e')
            ->leftJoin('mm_products as p', 'p.id', '=', 'e.product_id')
            ->leftJoin('mm_product_units as u', 'u.id', '=', 'e.uom_id')
            ->whereNotNull('e.product_id')
            ->groupBy('e.product_id', 'e.uom_id', 'p.title', 'u.unit_code', 'u.unit_name')
            ->orderBy('p.title')->orderBy('e.uom_id')
            ->selectRaw("COALESCE(p.title, 'N/A') AS product_name, COALESCE(u.unit_code, u.unit_name, 'N/A') AS uom,
                SUM(COALESCE(baseline, 0) + CASE WHEN moved_at < ? THEN COALESCE(purchased,0) - COALESCE(issued,0) - COALESCE(consumed,0) ELSE 0 END) AS opening_qty,
                SUM(CASE WHEN moved_at BETWEEN ? AND ? THEN purchased ELSE 0 END) AS purchase_qty,
                SUM(CASE WHEN moved_at BETWEEN ? AND ? THEN issued ELSE 0 END) AS issued_qty,
                SUM(CASE WHEN moved_at BETWEEN ? AND ? THEN consumed ELSE 0 END) AS consumption_qty,
                SUM(CASE WHEN moved_at > ? THEN COALESCE(purchased,0) - COALESCE(issued,0) - COALESCE(consumed,0) ELSE 0 END) AS later_qty,
                SUM(current_qty) AS quantity", [$start, $start, $end, $start, $end, $start, $end, $end])
            ->get()->map(function ($row) {
                $row->period_closing = round($row->opening_qty + $row->purchase_qty - $row->issued_qty - $row->consumption_qty, 4);
                $recordedBalance = $row->period_closing + $row->later_qty;
                $row->balance_difference = round($row->quantity - $recordedBalance, 4);
                $row->closing_qty = round($recordedBalance + $row->balance_difference, 4);
                return (array) $row;
            });

        return ['transactions' => $rows, 'columns' => self::columns(), 'opening_balance' => 0];
    }

    public function targetName(array $params): string
    {
        return 'Stock Level Inventory Report';
    }
}
