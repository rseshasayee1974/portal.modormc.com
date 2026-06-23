<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\MixDesign;
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

        $cacheKey = "batching.dashboard.data." . auth()->id() . "." . $plantId;
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($plantId) {
            // 1. Core KPIs
            $kpis = $this->getCoreKPIs($plantId);

            // 2. Charts spec
            $productionTrend = $this->getProductionTrend($plantId);
            $gradeBreakdown = $this->getGradeBreakdown($plantId);

            // 3. The Ledger Grid (Table 1)
            $ledger = $this->getLedgerData($plantId);

            // 4. Mix Designs Recipe Catalog (Table 2)
            $recipes = $this->getMixRecipes($plantId);

            return [
                'success' => true,
                'generated_at' => now()->toIso8601String(),
                'kpis' => $kpis,
                'charts' => [
                    'trend' => $productionTrend,
                    'distribution' => $gradeBreakdown
                ],
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

    private function getCoreKPIs(?int $plantId): array
    {
        if (!$plantId) {
            return [
                'total_volume' => 0,
                'active_batches' => 0,
                'deviation_rate' => 0,
                'avg_batch_size' => 0,
            ];
        }

        // Completed/dispatched batches volume
        $totalVolume = (float) Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->sum('batch_size');

        // Active batches (Planned, Loading, Dispatched)
        $activeBatches = Batch::where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_PLANNED, Batch::STATUS_LOADING, Batch::STATUS_DISPATCHED])
            ->count();

        // Recipe deviation rate calculation (>5% target deviation in materials)
        $totalBatchesWithActuals = Batch::where('plant_id', $plantId)
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
            ->avg('batch_size');

        return [
            'total_volume' => round($totalVolume, 2),
            'active_batches' => $activeBatches,
            'deviation_rate' => $deviationRate,
            'avg_batch_size' => round($avgBatchSize, 2),
        ];
    }

    private function getProductionTrend(?int $plantId): array
    {
        $labels = [];
        $volumes = [];
        $counts = [];

        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $statsMap = collect();
        if ($plantId) {
            $statsMap = DB::table('mm_batches')
                ->where('plant_id', $plantId)
                ->whereNull('deleted_at')
                ->whereBetween('start_time', [$startDate, $endDate])
                ->whereIn('status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
                ->selectRaw('DATE(start_time) as date_label, SUM(batch_size) as total_volume, COUNT(id) as batch_count')
                ->groupBy(DB::raw('DATE(start_time)'))
                ->get()
                ->keyBy('date_label');
        }

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
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

    private function getGradeBreakdown(?int $plantId): array
    {
        if (!$plantId) return [];

        $raw = DB::table('mm_batches')
            ->join('mm_sales_orders', 'mm_batches.sales_order_id', '=', 'mm_sales_orders.id')
            ->join('mm_mix_designs', 'mm_sales_orders.mix_design_id', '=', 'mm_mix_designs.id')
            ->where('mm_batches.plant_id', $plantId)
            ->whereNull('mm_batches.deleted_at')
            ->whereIn('mm_batches.status', [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
            ->select('mm_mix_designs.design_name as grade', DB::raw('SUM(mm_batches.batch_size) as total_volume'))
            ->groupBy('mm_mix_designs.design_name')
            ->get();

        return $raw->map(fn($row) => [
            'grade' => $row->grade,
            'value' => round((float) $row->total_volume, 2),
        ])->all();
    }

    private function getLedgerData(?int $plantId): array
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
        ->latest('start_time')
        ->limit(50)
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
            ->where('is_active', true)
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