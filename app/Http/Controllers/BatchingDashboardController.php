<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\MixDesign;
use App\Models\Quotation;
use App\Models\CustomerPO;
use App\Models\SalesOrder;
use App\Models\Dispatch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BatchingDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $plantId = $this->resolvePlantId();
        $plants = Cache::remember("plants", now()->addDays(7), function () {
            return Plant::all(['id', 'name']);
        });

        return Inertia::render('Dashboard/BatchingDashboard', [
            'plants' => $plants,
            'activePlantId' => $plantId,
        ]);
    }

    public function getData(Request $request)
    {
        $plantId = $this->resolvePlantId();

        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $start = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : now()->subDays(29)->startOfDay();
        $end = $endDateInput ? Carbon::parse($endDateInput)->endOfDay() : now()->endOfDay();

        $cacheKey = "batching.dashboard.data." . auth()->id() . "." . $plantId . "." . $start->toDateString() . "." . $end->toDateString();
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($plantId, $start, $end) {
            // 1. Core KPIs
            $kpis = $this->getCoreKPIs($plantId, $start, $end);

            // 2. Charts spec
            $productionTrend = $this->getProductionTrend($plantId, $start, $end);
            $gradeBreakdown = $this->getGradeBreakdown($plantId, $start, $end);

            // 3. Module Specific Metrics
            $quotations = $this->getQuotationMetrics($plantId, $start, $end);
            $customerPOs = $this->getCustomerPOMetrics($plantId, $start, $end);
            $salesOrders = $this->getSalesOrderMetrics($plantId, $start, $end);
            $production = $this->getProductionMetrics($plantId);

            // 4. The Ledger Grid
            $ledger = $this->getLedgerData($plantId, $start, $end);

            // 5. Mix Designs Recipe Catalog
            $recipes = $this->getMixRecipes($plantId);

            return [
                'success' => true,
                'generated_at' => now()->toIso8601String(),
                'kpis' => $kpis,
                'charts' => [
                    'trend' => $productionTrend,
                    'distribution' => $gradeBreakdown
                ],
                'quotations' => $quotations,
                'customer_pos' => $customerPOs,
                'sales_orders' => $salesOrders,
                'production' => $production,
                'ledger' => $ledger,
                'recipes' => $recipes,
            ];
        });

        return response()->json($payload);
    }

    private function resolvePlantId(): ?int
    {
        $plantId = session('active_plant_id');
        if ($plantId) {
            return (int) $plantId;
        }
        return Plant::where('is_active', true)->value('id') ?? Plant::value('id');
    }

    private function getCoreKPIs(?int $plantId, Carbon $start, Carbon $end): array
    {
        if (!$plantId) {
            return [
                'total_volume' => 0,
                'active_batches' => 0,
                'deviation_rate' => 0,
                'avg_batch_size' => 0,
            ];
        }

        // Completed/dispatched batches volume in date range
        $totalVolume = (float) Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->whereBetween('start_time', [$start, $end])
            ->sum('batch_size');

        // Active batches (Planned, Loading, Dispatched) in date range
        $activeBatches = Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_PLANNED, Batch::STATUS_LOADING, Batch::STATUS_DISPATCHED])
            ->whereBetween('start_time', [$start, $end])
            ->count();

        // Recipe deviation rate calculation (>5% target deviation in materials)
        $totalBatchesWithActuals = Batch::where('plant_id', $plantId)
            ->whereBetween('start_time', [$start, $end])
            ->whereHas('materials', function ($q) {
                $q->where('actual_qty', '>', 0);
            })
            ->count();

        $deviatedBatches = 0;
        if ($totalBatchesWithActuals > 0) {
            // Find batch IDs that have at least one material with > 5% deviation
            $deviatedBatches = DB::table('mm_batch_materials')
                ->join('mm_batches', 'mm_batch_materials.batch_id', '=', 'mm_batches.id')
                ->where('mm_batches.plant_id', $plantId)
                ->whereNull('mm_batches.deleted_at')
                ->whereBetween('mm_batches.start_time', [$start, $end])
                ->where('mm_batch_materials.target_qty', '>', 0)
                ->whereRaw('ABS(mm_batch_materials.actual_qty - mm_batch_materials.target_qty) / mm_batch_materials.target_qty > 0.05')
                ->distinct('mm_batch_materials.batch_id')
                ->count('mm_batch_materials.batch_id');
        }

        $deviationRate = $totalBatchesWithActuals > 0 
            ? round(($deviatedBatches / $totalBatchesWithActuals) * 100, 1) 
            : 0;

        // Average Batch Size
        $avgBatchSize = (float) Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->whereBetween('start_time', [$start, $end])
            ->avg('batch_size');

        return [
            'total_volume' => round($totalVolume, 2),
            'active_batches' => $activeBatches,
            'deviation_rate' => $deviationRate,
            'avg_batch_size' => round($avgBatchSize, 2),
        ];
    }

    private function getProductionTrend(?int $plantId, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $volumes = [];
        $counts = [];

        $statsMap = collect();
        if ($plantId) {
            $statsMap = DB::table('mm_batches')
                ->where('plant_id', $plantId)
                ->whereNull('deleted_at')
                ->whereBetween('start_time', [$start, $end])
                ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
                ->selectRaw('DATE(start_time) as date_label, SUM(batch_size) as total_volume, COUNT(id) as batch_count')
                ->groupBy(DB::raw('DATE(start_time)'))
                ->get()
                ->keyBy('date_label');
        }

        $diffInDays = $start->diffInDays($end);
        for ($i = $diffInDays; $i >= 0; $i--) {
            $date = (clone $end)->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('d M');

            $dayStats = $statsMap->get($dateStr);
            $volumes[] = $dayStats ? round((float) $dayStats->total_volume, 2) : 0;
            $counts[] = $dayStats ? (int) $dayStats->batch_count : 0;
        }

        return [
            'labels' => $labels,
            'volumes' => $volumes,
            'counts' => $counts,
        ];
    }

    private function getGradeBreakdown(?int $plantId, Carbon $start, Carbon $end): array
    {
        if (!$plantId) return [];

        $raw = DB::table('mm_batches')
            ->join('mm_sales_orders', 'mm_batches.sales_order_id', '=', 'mm_sales_orders.id')
            ->join('mm_mix_designs', 'mm_sales_orders.mix_design_id', '=', 'mm_mix_designs.id')
            ->where('mm_batches.plant_id', $plantId)
            ->whereNull('mm_batches.deleted_at')
            ->whereBetween('mm_batches.start_time', [$start, $end])
            ->whereIn('mm_batches.status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->select('mm_mix_designs.design_name as grade', DB::raw('SUM(mm_batches.batch_size) as total_volume'))
            ->groupBy('mm_mix_designs.design_name')
            ->get();

        return $raw->map(fn($row) => [
            'grade' => $row->grade,
            'value' => round((float) $row->total_volume, 2),
        ])->all();
    }

    private function getQuotationMetrics(?int $plantId, Carbon $start, Carbon $end): array
    {
        if (!$plantId) {
            return [
                'total' => 0,
                'draft' => 0,
                'sent' => 0,
                'accepted' => 0,
                'rejected' => 0,
                'total_quantity' => 0,
                'top_selling' => [],
            ];
        }

        $quotes = Quotation::where('plant_id', $plantId)
            ->whereBetween('quote_date', [$start->toDateString(), $end->toDateString()]);

        $totalQuotes = (clone $quotes)->count();
        $draft = (clone $quotes)->where('status', 0)->count();
        $sent = (clone $quotes)->where('status', 1)->count();
        $accepted = (clone $quotes)->where('status', 2)->count();
        $rejected = (clone $quotes)->where('status', 3)->count();

        // Total quantity in quotation items
        $totalQty = (float) DB::table('mm_quotation_items')
            ->join('mm_quotations', 'mm_quotation_items.quotation_id', '=', 'mm_quotations.id')
            ->where('mm_quotations.plant_id', $plantId)
            ->whereNull('mm_quotations.deleted_at')
            ->whereBetween('mm_quotations.quote_date', [$start->toDateString(), $end->toDateString()])
            ->sum('mm_quotation_items.quantity');

        // Top selling mix design
        $topSelling = DB::table('mm_quotation_items')
            ->join('mm_quotations', 'mm_quotation_items.quotation_id', '=', 'mm_quotations.id')
            ->join('mm_mix_designs', 'mm_quotation_items.mix_design_id', '=', 'mm_mix_designs.id')
            ->where('mm_quotations.plant_id', $plantId)
            ->whereNull('mm_quotations.deleted_at')
            ->whereBetween('mm_quotations.quote_date', [$start->toDateString(), $end->toDateString()])
            ->select(
                'mm_mix_designs.design_name as name',
                'mm_mix_designs.design_code as code',
                DB::raw('SUM(mm_quotation_items.quantity) as total_qty'),
                DB::raw('SUM(mm_quotation_items.amount_total) as total_amount')
            )
            ->groupBy('mm_mix_designs.design_name', 'mm_mix_designs.design_code')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'total' => $totalQuotes,
            'draft' => $draft,
            'sent' => $sent,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'total_quantity' => round($totalQty, 2),
            'top_selling' => $topSelling,
        ];
    }

    private function getCustomerPOMetrics(?int $plantId, Carbon $start, Carbon $end): array
    {
        if (!$plantId) {
            return [
                'total' => 0,
                'confirmed' => 0,
                'draft' => 0,
                'total_value' => 0,
                'converted_list' => [],
            ];
        }

        $pos = CustomerPO::where('plant_id', $plantId)
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()]);

        $totalPOs = (clone $pos)->count();
        $confirmed = (clone $pos)->where('status', 1)->count();
        $draft = (clone $pos)->where('status', 0)->count();

        // Total Value
        $totalVal = (float) DB::table('mm_customer_po_items')
            ->join('mm_customer_pos', 'mm_customer_po_items.customer_po_id', '=', 'mm_customer_pos.id')
            ->where('mm_customer_pos.plant_id', $plantId)
            ->whereNull('mm_customer_pos.deleted_at')
            ->whereBetween('mm_customer_pos.order_date', [$start->toDateString(), $end->toDateString()])
            ->sum('mm_customer_po_items.amount_total');

        // Converted list
        $list = CustomerPO::with(['patron:id,legal_name', 'site:id,name', 'quotation:id,reference'])
            ->where('plant_id', $plantId)
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->latest('order_date')
            ->limit(10)
            ->get()
            ->map(fn($po) => [
                'id' => $po->id,
                'reference' => ($po->reference ?? ''),
                'customer' => $po->patron->legal_name ?? 'N/A',
                'site' => $po->site->name ?? 'N/A',
                'quote' => $po->quotation->reference ?? 'N/A',
                'amount' => (float) $po->amount_total,
                'status' => $po->status == 1 ? 'Confirmed' : 'Draft',
            ])
            ->toArray();

        return [
            'total' => $totalPOs,
            'confirmed' => $confirmed,
            'draft' => $draft,
            'total_value' => round($totalVal, 2),
            'converted_list' => $list,
        ];
    }

    private function getSalesOrderMetrics(?int $plantId, Carbon $start, Carbon $end): array
    {
        if (!$plantId) {
            return [
                'total' => 0,
                'converted' => 0,
                'scheduled' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'total_qty' => 0,
                'produced_qty' => 0,
            ];
        }

        $sos = SalesOrder::where('plant_id', $plantId)
            ->whereBetween('created_at', [$start, $end]);

        $totalSOs = (clone $sos)->count();
        $converted = (clone $sos)->whereNotNull('customer_po_id')->count();
        $scheduled = (clone $sos)->where('status', 1)->count();
        $inProgress = (clone $sos)->where('status', 2)->count();
        $completed = (clone $sos)->where('status', 3)->count();
        $cancelled = (clone $sos)->where('status', 4)->count();

        $totalQty = (float) (clone $sos)->sum('total_qty');
        $producedQty = (float) (clone $sos)->sum('produced_qty');

        return [
            'total' => $totalSOs,
            'converted' => $converted,
            'scheduled' => $scheduled,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'total_qty' => round($totalQty, 2),
            'produced_qty' => round($producedQty, 2),
        ];
    }

    private function getProductionMetrics(?int $plantId): array
    {
        if (!$plantId) {
            return [
                'today_volume' => 0,
                'week_volume' => 0,
                'month_volume' => 0,
                'today_dispatched' => 0,
                'week_dispatched' => 0,
                'month_dispatched' => 0,
            ];
        }

        // Today
        $todayVolume = (float) Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->whereDate('start_time', Carbon::today())
            ->sum('batch_size');

        // This Week (starting Monday)
        $weekVolume = (float) Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->whereBetween('start_time', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('batch_size');

        // This Month
        $monthVolume = (float) Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->whereMonth('start_time', Carbon::now()->month)
            ->whereYear('start_time', Carbon::now()->year)
            ->sum('batch_size');

        // Dispatched volume
        $todayDisp = (float) Dispatch::where('plant_id', $plantId)
            ->whereDate('dispatch_time', Carbon::today())
            ->sum('delivered_qty');

        $weekDisp = (float) Dispatch::where('plant_id', $plantId)
            ->whereBetween('dispatch_time', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('delivered_qty');

        $monthDisp = (float) Dispatch::where('plant_id', $plantId)
            ->whereMonth('dispatch_time', Carbon::now()->month)
            ->whereYear('dispatch_time', Carbon::now()->year)
            ->sum('delivered_qty');

        return [
            'today_volume' => round($todayVolume, 2),
            'week_volume' => round($weekVolume, 2),
            'month_volume' => round($monthVolume, 2),
            'today_dispatched' => round($todayDisp, 2),
            'week_dispatched' => round($weekDisp, 2),
            'month_dispatched' => round($monthDisp, 2),
        ];
    }

    private function getLedgerData(?int $plantId, Carbon $start, Carbon $end): array
    {
        if (!$plantId) return [];

        $batches = Batch::with([
            'workOrder:id,order_no,customer_id,mix_design_id,site_id',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.mixDesign:id,design_name,design_code',
            'operator:id,first_name,last_name',
            'truck:id,registration',
            'driver:id,first_name,last_name'
        ])
        ->where('plant_id', $plantId)
        ->whereBetween('start_time', [$start, $end])
        ->latest('start_time')
        ->limit(100)
        ->get();

        $batchIds = $batches->pluck('id')->all();
        $deviations = collect();
        if (!empty($batchIds)) {
            $deviations = DB::table('mm_batch_materials')
                ->whereIn('batch_id', $batchIds)
                ->where('target_qty', '>', 0)
                ->whereRaw('ABS(actual_qty - target_qty) / target_qty > 0.05')
                ->distinct()
                ->pluck('batch_id')
                ->flip();
        }

        return $batches->map(function ($batch) use ($deviations) {
            $hasDeviation = isset($deviations[$batch->id]);

            return [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no ?? ('B-' . $batch->id),
                'work_order' => $batch->workOrder->order_no ?? 'N/A',
                'customer' => $batch->workOrder->customer->legal_name ?? 'N/A',
                'site' => $batch->workOrder->site->name ?? 'N/A',
                'mix_design' => $batch->workOrder->mixDesign->design_name ?? 'N/A',
                'mix_code' => $batch->workOrder->mixDesign->design_code ?? 'N/A',
                'batch_size' => (float) $batch->batch_size,
                'start_time' => $batch->start_time ? $batch->start_time->toIso8601String() : null,
                'status' => Batch::statusLabel($batch->status),
                'status_id' => $batch->status,
                'operator' => $batch->operator ? ($batch->operator->first_name . ' ' . $batch->operator->last_name) : 'N/A',
                'truck' => $batch->truck->registration ?? 'N/A',
                'driver' => $batch->driver ? ($batch->driver->first_name . ' ' . $batch->driver->last_name) : 'N/A',
                'sync_status' => $batch->sync_status ?? 'pending',
                'has_deviation' => $hasDeviation,
            ];
        })->all();
    }

    private function getMixRecipes(?int $plantId): array
    {
        if (!$plantId) return [];

        $mixes = MixDesign::with(['items.product', 'items.uom'])
            ->where('plant_id', $plantId)
            ->limit(30)
            ->get();

        return $mixes->map(function ($mix) {
            return [
                'id' => $mix->id,
                'design_name' => $mix->design_name,
                'design_code' => $mix->design_code,
                'grade' => $mix->grade ?? 'N/A',
                'materials' => $mix->items->map(function ($item) {
                    return [
                        'name' => $item->product->title ?? 'N/A',
                        'qty' => (float) $item->actual_quantity,
                        'uom' => $item->uom->unit_code ?? 'kg',
                    ];
                })->all()
            ];
        })->all();
    }
}