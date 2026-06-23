<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Product;
use App\Models\Quantity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InventoryDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $plantId = $this->resolvePlantId();
        $plants = Cache::remember("plants", now()->addDays(7), function () {
            return Plant::all(['id', 'name']);
        });

        $cacheKey = "inventory.dashboard.data.{$plantId}";
        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $dashboardData = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($plantId) {
            $products = collect();
            $quantities = collect();
            if ($plantId) {
                $products = Product::where('plant_id', $plantId)
                    ->with(['category', 'unit'])
                    ->get();

                $quantities = Quantity::where('plant_id', $plantId)
                    ->selectRaw('product_id, SUM(quantity) as qty, MAX(date) as last_date')
                    ->groupBy('product_id')
                    ->get()
                    ->keyBy('product_id');
            }

            $stockSummary = $this->getStockSummary($plantId, $products, $quantities);
            $categoryBreakdown = $this->getCategoryBreakdown($plantId, $products, $quantities);
            $recentInwards = $this->getRecentInwards($plantId);
            $lowStockItems = $this->getLowStockItems($plantId, $products, $quantities);
            $abcAnalysis = $this->getABCAnalysis($plantId, $products, $quantities);
            $monthlyTrend = $this->getMonthlyTrend($plantId);
            $ledger = $this->getLedgerData($plantId, $products, $quantities);

            return [
                'metrics' => [
                    'total_valuation' => $stockSummary['total_valuation'],
                    'total_items' => $stockSummary['total_items'],
                    'low_stock_count' => $stockSummary['low_stock_count'],
                    'inbound_30d_volume' => round($plantId ? PurchaseOrderHistory::where('plant_id', $plantId)->where('received_date', '>=', now()->subDays(30))->sum('received_qty') : 0, 2),
                    'outbound_30d_volume' => round($plantId ? DB::table('mm_dispatches')->where('plant_id', $plantId)->where('dispatch_time', '>=', now()->subDays(30))->sum('delivered_qty') * 1.5 : 0, 2),
                    'open_pos_count' => $plantId ? PurchaseOrder::where('plant_id', $plantId)->where('invoice_status', 0)->count() : 0,
                    'recent_inwards_count' => $plantId ? PurchaseOrderHistory::where('plant_id', $plantId)->whereMonth('received_date', now()->month)->count() : 0,
                ],
                'categoryBreakdown' => $categoryBreakdown,
                'recentInwards' => $recentInwards,
                'lowStockItems' => $lowStockItems,
                'abcAnalysis' => $abcAnalysis,
                'monthlyTrend' => $monthlyTrend,
                'ledger' => $ledger,
            ];
        });

        return Inertia::render('Dashboard/InventoryDashboard', array_merge([
            'plants' => $plants,
            'activePlantId' => $plantId,
        ], $dashboardData));
    }

    public function adjust(Request $request, int $id)
    {
        $plantId = $this->resolvePlantId();
        $qtyValue = (float) $request->input('quantity');

        $quantity = Quantity::where('plant_id', $plantId)
            ->where('product_id', $id)
            ->first();

        if ($quantity) {
            $quantity->update([
                'quantity' => $qtyValue,
                'date' => now()
            ]);
        } else {
            Quantity::create([
                'plant_id' => $plantId,
                'product_id' => $id,
                'quantity' => $qtyValue,
                'date' => now()
            ]);
        }

        $products = Product::where('plant_id', $plantId)->with(['category', 'unit'])->get();
        $quantities = Quantity::where('plant_id', $plantId)
            ->selectRaw('product_id, SUM(quantity) as qty, MAX(date) as last_date')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $stockSummary = $this->getStockSummary($plantId, $products, $quantities);

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_valuation' => $stockSummary['total_valuation'],
                'total_items' => $stockSummary['total_items'],
                'low_stock_count' => $stockSummary['low_stock_count'],
                'inbound_30d_volume' => round(PurchaseOrderHistory::where('plant_id', $plantId)->where('received_date', '>=', now()->subDays(30))->sum('received_qty'), 2),
                'outbound_30d_volume' => round(DB::table('mm_dispatches')->where('plant_id', $plantId)->where('dispatch_time', '>=', now()->subDays(30))->sum('delivered_qty') * 1.5, 2),
            ]
        ]);
    }

    private function resolvePlantId(): ?int
    {
        $plantId = session('active_plant_id');
        if ($plantId) {
            return (int) $plantId;
        }
        return Plant::where('is_active', true)->value('id') ?? Plant::value('id');
    }

    private function getStockSummary(?int $plantId, $products, $quantities): array
    {
        if (!$plantId) {
            return ['total_valuation' => 0, 'total_items' => 0, 'low_stock_count' => 0];
        }

        $totalValuation = 0;
        $totalItems = 0;
        $lowStockCount = 0;

        foreach ($products as $product) {
            $qtyRecord = $quantities->get($product->id);
            $qty = $qtyRecord ? (float) $qtyRecord->qty : 0;
            if ($qty > 0) {
                $totalValuation += $qty * ($product->purchase_price ?? 0);
                $totalItems++;
            }
            if ($product->stock_alert > 0 && $qty <= $product->stock_alert) {
                $lowStockCount++;
            }
        }

        return [
            'total_valuation' => round($totalValuation, 2),
            'total_items' => $totalItems,
            'low_stock_count' => $lowStockCount,
        ];
    }

    private function getCategoryBreakdown(?int $plantId, $products, $quantities): array
    {
        if (!$plantId) return [];

        $breakdown = [];

        foreach ($products as $product) {
            $qtyRecord = $quantities->get($product->id);
            $qty = $qtyRecord ? (float) $qtyRecord->qty : 0;
            if ($qty > 0) {
                $categoryName = $product->category->category_name ?? 'Uncategorized';
                if (!isset($breakdown[$categoryName])) {
                    $breakdown[$categoryName] = 0;
                }
                $breakdown[$categoryName] += $qty * ($product->purchase_price ?? 0);
            }
        }

        return collect($breakdown)->map(fn($val, $key) => [
            'category' => $key,
            'value' => round($val, 2),
        ])->values()->all();
    }

    private function getRecentInwards(?int $plantId): array
    {
        if (!$plantId) return [];

        return PurchaseOrderHistory::where('plant_id', $plantId)
            ->with(['order.vendor', 'product'])
            ->latest('received_date')
            ->limit(6)
            ->get()
            ->map(fn($inward) => [
                'id' => $inward->id,
                'po_number' => $inward->order->po_number ?? 'N/A',
                'vendor' => $inward->order->vendor->legal_name ?? 'N/A',
                'product' => $inward->product->title ?? 'N/A',
                'qty' => $inward->received_qty,
                'date' => optional($inward->received_date)->format('d M Y') ?? 'N/A',
            ])->all();
    }

    private function getLowStockItems(?int $plantId, $products, $quantities): array
    {
        if (!$plantId) return [];

        return $products
            ->where('stock_alert', '>', 0)
            ->map(function ($product) use ($quantities) {
                $qtyRecord = $quantities->get($product->id);
                $qty = $qtyRecord ? (float) $qtyRecord->qty : 0;
                return [
                    'id' => $product->id,
                    'name' => $product->title,
                    'quantity' => $qty,
                    'alert_level' => $product->stock_alert,
                    'is_critical' => $qty <= $product->stock_alert,
                ];
            })
            ->filter(fn($item) => $item['is_critical'])
            ->values()
            ->all();
    }

    private function getABCAnalysis(?int $plantId, $products, $quantities): array
    {
        if (!$plantId) return [];

        $items = [];
        $totalVal = 0;

        foreach ($products as $product) {
            $qtyRecord = $quantities->get($product->id);
            $qty = $qtyRecord ? (float) $qtyRecord->qty : 0;
            $val = $qty * ($product->purchase_price ?? 0);
            if ($val > 0) {
                $items[] = [
                    'name' => $product->title,
                    'value' => $val,
                ];
                $totalVal += $val;
            }
        }

        if ($totalVal == 0) return [];

        usort($items, fn($a, $b) => $b['value'] <=> $a['value']);

        $classA = 0;
        $classB = 0;
        $classC = 0;
        $runningSum = 0;

        foreach ($items as $item) {
            $runningSum += $item['value'];
            $percent = ($runningSum / $totalVal) * 100;
            if ($percent <= 70) {
                $classA += $item['value'];
            } elseif ($percent <= 90) {
                $classB += $item['value'];
            } else {
                $classC += $item['value'];
            }
        }

        $itemsCount = count($items);
        return [
            ['class' => 'Class A (High Value)', 'value' => round($classA, 2), 'count' => count(array_filter($items, fn($i, $idx) => (($idx + 1) / $itemsCount) <= 0.2, ARRAY_FILTER_USE_BOTH))],
            ['class' => 'Class B (Medium Value)', 'value' => round($classB, 2), 'count' => count(array_filter($items, fn($i, $idx) => (($idx + 1) / $itemsCount) > 0.2 && (($idx + 1) / $itemsCount) <= 0.5, ARRAY_FILTER_USE_BOTH))],
            ['class' => 'Class C (Low Value)', 'value' => round($classC, 2), 'count' => count(array_filter($items, fn($i, $idx) => (($idx + 1) / $itemsCount) > 0.5, ARRAY_FILTER_USE_BOTH))],
        ];
    }

    private function getMonthlyTrend(?int $plantId): array
    {
        $months = [];
        $receipts = [];
        $issues = [];

        $startDate = now()->subMonths(5)->startOfMonth();
        $endDate = now()->endOfMonth();

        $inboundData = collect();
        $outboundData = collect();

        if ($plantId) {
            $inboundData = PurchaseOrderHistory::where('plant_id', $plantId)
                ->whereBetween('received_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw("DATE_FORMAT(received_date, '%Y-%m') as month_key, SUM(received_qty) as total_inbound")
                ->groupBy(DB::raw("DATE_FORMAT(received_date, '%Y-%m')"))
                ->pluck('total_inbound', 'month_key');

            $outboundData = DB::table('mm_dispatches')
                ->where('plant_id', $plantId)
                ->whereBetween('dispatch_time', [$startDate, $endDate])
                ->selectRaw("DATE_FORMAT(dispatch_time, '%Y-%m') as month_key, SUM(delivered_qty) as total_outbound")
                ->groupBy(DB::raw("DATE_FORMAT(dispatch_time, '%Y-%m')"))
                ->pluck('total_outbound', 'month_key');
        }

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');
            $key = $date->format('Y-m');

            $inbound = $inboundData->get($key, 0);
            $outbound = $outboundData->get($key, 0) * 1.5;

            $receipts[] = round((float) $inbound, 2);
            $issues[] = round((float) $outbound, 2);
        }

        return [
            'labels' => $months,
            'receipts' => $receipts,
            'issues' => $issues,
        ];
    }

    private function getLedgerData(?int $plantId, $products, $quantities): array
    {
        if (!$plantId) return [];

        return $products->map(function ($product) use ($quantities) {
            $qtyRecord = $quantities->get($product->id);
            $qty = $qtyRecord ? (float) $qtyRecord->qty : 0.0;
            $lastUpdated = $qtyRecord && $qtyRecord->last_date ? $qtyRecord->last_date : now()->toIso8601String();
            
            $status = 'Healthy';
            if ($product->stock_alert > 0 && $qty == 0) {
                $status = 'Out of Stock';
            } elseif ($product->stock_alert > 0 && $qty <= $product->stock_alert) {
                $status = 'Low Stock';
            }

            return [
                'id' => $product->id,
                'sku' => $product->sku ?? ('SKU-' . str_pad($product->id, 4, '0', STR_PAD_LEFT)),
                'name' => $product->title,
                'category' => $product->category->category_name ?? 'Uncategorized',
                'quantity' => $qty,
                'uom' => $product->unit->unit_name ?? 'Unit',
                'alert_threshold' => (float) $product->stock_alert,
                'unit_cost' => (float) ($product->purchase_price ?? 0),
                'status' => $status,
                'last_updated' => $lastUpdated,
            ];
        })->values()->all();
    }
}