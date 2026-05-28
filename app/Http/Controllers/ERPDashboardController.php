<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quantity;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ERPDashboardController extends Controller
{
    public function index(Request $request)
    {
        $plantId = $this->resolvePlantId();
        [$start, $end] = $this->resolveDateRange(
            $request->input('start_date'),
            $request->input('end_date')
        );
        $patronId = $request->filled('patron_id') ? (int) $request->input('patron_id') : null;

        $patrons = Patron::query()
            ->when($plantId, fn ($query) => $query->where('plant_id', $plantId))
            ->orderBy('legal_name')
            ->get(['id', 'legal_name']);

        return Inertia::render('Dashboard/Dashboard', [
            'patrons' => $patrons,
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'patron_id' => $patronId,
            ],
            'initialData' => $plantId
                ? $this->buildDashboardPayload($plantId, $start, $end, $patronId)
                : $this->emptyPayload(),
        ]);
    }

    public function getData(Request $request)
    {
        $plantId = $this->resolvePlantId();

        if (!$plantId) {
            return response()->json($this->emptyPayload());
        }

        [$start, $end] = $this->resolveDateRange(
            $request->input('start_date'),
            $request->input('end_date')
        );

        $patronId = $request->filled('patron_id') ? (int) $request->input('patron_id') : null;

        return response()->json($this->buildDashboardPayload($plantId, $start, $end, $patronId));
    }

    private function buildDashboardPayload(int $plantId, Carbon $start, Carbon $end, ?int $patronId): array
    {
        $stockSnapshot = $this->getStockSnapshot($plantId);
        $stockOverview = $this->getStockOverview($plantId);

        return [
            'generated_at' => now()->toIso8601String(),
            'metrics' => $this->getMetrics($plantId, $start, $end, $patronId, $stockOverview),
            'module_cards' => $this->getModuleCards($plantId, $start, $end, $patronId, $stockOverview),
            'finance_trend' => $this->getFinanceTrend($plantId, $start, $end, $patronId),
            'dispatch_status' => $this->getDispatchStatusBreakdown($plantId, $start, $end, $patronId),
            'customer_leaderboard' => $this->getCustomerLeaderboard($plantId, $start, $end, $patronId),
            'stock_snapshot' => $stockSnapshot,
            'stock_alerts' => collect($stockSnapshot)->where('is_critical', true)->values(),
            'recent_transactions' => $this->getRecentActivity($plantId, $patronId),
            'work_orders' => $this->getRecentWorkOrders($plantId, $patronId),
            'dispatches' => $this->getRecentDispatches($plantId, $patronId),
            'purchase_orders' => $this->getRecentPurchaseOrders($plantId, $patronId),
        ];
    }

    private function resolvePlantId(): ?int
    {
        $plantId = session('active_plant_id');

        if ($plantId) {
            return (int) $plantId;
        }

        return Invoice::latest('invoice_date')->value('plant_id')
            ?? WorkOrder::latest()->value('plant_id')
            ?? Plant::where('is_active', true)->value('id');
    }

    private function resolveDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(29)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function salesInvoiceQuery(int $plantId, Carbon $start, Carbon $end, ?int $patronId = null)
    {
        $table = (new Invoice)->getTable();
        return Invoice::query()
            ->where("{$table}.plant_id", $plantId)
            ->where("{$table}.invoice_type", 'sales')
            ->whereBetween("{$table}.invoice_date", [$start->toDateString(), $end->toDateString()])
            ->when($patronId, fn ($query) => $query->where("{$table}.partner_id", $patronId));
    }

    private function purchaseOrderQuery(int $plantId, Carbon $start, Carbon $end, ?int $patronId = null)
    {
        $table = (new PurchaseOrder)->getTable();
        return PurchaseOrder::query()
            ->where("{$table}.plant_id", $plantId)
            ->whereBetween("{$table}.date_order", [$start->toDateString(), $end->toDateString()])
            ->when($patronId, fn ($query) => $query->where("{$table}.vendor_id", $patronId));
    }

    private function dispatchQuery(int $plantId, Carbon $start, Carbon $end, ?int $patronId = null)
    {
        $table = (new Dispatch)->getTable();
        return Dispatch::query()
            ->where("{$table}.plant_id", $plantId)
            ->whereBetween("{$table}.dispatch_time", [$start, $end])
            ->when($patronId, fn ($query) => $query->where("{$table}.customer_id", $patronId));
    }

    private function journalLineQuery(int $plantId, Carbon $start, Carbon $end, string $voucherType, ?int $patronId = null)
    {
        $table = (new JournalEntryLine)->getTable();
        return JournalEntryLine::query()
            ->where("{$table}.plant_id", $plantId)
            ->when($patronId, fn ($query) => $query->where("{$table}.partner_id", $patronId))
            ->whereHas('entry', function ($query) use ($voucherType, $start, $end) {
                $query
                    ->where('voucher_type', $voucherType)
                    ->whereBetween('voucher_date', [$start->toDateString(), $end->toDateString()]);
            });
    }

    private function getMetrics(int $plantId, Carbon $start, Carbon $end, ?int $patronId, array $stockOverview): array
    {
        $salesRevenue = (float) $this->salesInvoiceQuery($plantId, $start, $end, $patronId)->sum('total_amount');
        $purchaseSpend = (float) $this->purchaseOrderQuery($plantId, $start, $end, $patronId)->sum('amount_total');
        $dispatchRevenue = (float) $this->dispatchQuery($plantId, $start, $end, $patronId)->sum('load_total_amount');
        $dispatchQuantity = (float) $this->dispatchQuery($plantId, $start, $end, $patronId)->sum('delivered_qty');
        $dispatchTrips = (int) $this->dispatchQuery($plantId, $start, $end, $patronId)->count();
        $collections = (float) $this->journalLineQuery($plantId, $start, $end, 'RECEIPT', $patronId)->sum('credit_amount');
        $payments = (float) $this->journalLineQuery($plantId, $start, $end, 'PAYMENT', $patronId)->sum('debit_amount');

        $receivables = (float) Invoice::query()
            ->where('plant_id', $plantId)
            ->where('invoice_type', 'sales')
            ->when($patronId, fn ($query) => $query->where('partner_id', $patronId))
            ->sum('balance_amount');

        $payables = (float) Invoice::query()
            ->where('plant_id', $plantId)
            ->where('invoice_type', 'bill')
            ->when($patronId, fn ($query) => $query->where('partner_id', $patronId))
            ->sum('balance_amount');

        $stockValue = (float) ($stockOverview['stock_value'] ?? 0);
        $lowStockCount = (int) ($stockOverview['low_stock_count'] ?? 0);
        $openWorkOrders = WorkOrder::query()
            ->where('plant_id', $plantId)
            ->when($patronId, fn ($query) => $query->where('customer_id', $patronId))
            ->whereIn('status', [WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_IN_PROGRESS])
            ->count();

        $activeBatches = Batch::query()
            ->where('plant_id', $plantId)
            ->whereIn('status', [Batch::STATUS_PLANNED, Batch::STATUS_LOADING, Batch::STATUS_DISPATCHED])
            ->whereHas('workOrder', function ($query) use ($patronId) {
                if ($patronId) {
                    $query->where('customer_id', $patronId);
                }
            })
            ->count();

        return [
            'sales_revenue' => round($salesRevenue, 2),
            'purchase_spend' => round($purchaseSpend, 2),
            'dispatch_revenue' => round($dispatchRevenue, 2),
            'dispatch_quantity' => round($dispatchQuantity, 3),
            'dispatch_trips' => $dispatchTrips,
            'collections' => round($collections, 2),
            'payments' => round($payments, 2),
            'receivables' => round($receivables, 2),
            'payables' => round($payables, 2),
            'cash_delta' => round($collections - $payments, 2),
            'stock_value' => round((float) $stockValue, 2),
            'low_stock_count' => $lowStockCount,
            'open_work_orders' => $openWorkOrders,
            'active_batches' => $activeBatches,
        ];
    }

    private function getModuleCards(int $plantId, Carbon $start, Carbon $end, ?int $patronId, array $stockOverview): array
    {
        $salesInvoices = $this->salesInvoiceQuery($plantId, $start, $end, $patronId);
        $purchaseOrders = $this->purchaseOrderQuery($plantId, $start, $end, $patronId);
        $dispatches = $this->dispatchQuery($plantId, $start, $end, $patronId);
        $receipts = $this->journalLineQuery($plantId, $start, $end, 'RECEIPT', $patronId);
        $payments = $this->journalLineQuery($plantId, $start, $end, 'PAYMENT', $patronId);

        return [
            [
                'key' => 'sales',
                'title' => 'Sales',
                'value' => round((float) $salesInvoices->sum('total_amount'), 2),
                'meta' => $salesInvoices->count() . ' invoices',
                'accent' => 'amber',
            ],
            [
                'key' => 'purchase',
                'title' => 'Purchase',
                'value' => round((float) $purchaseOrders->sum('amount_total'), 2),
                'meta' => $purchaseOrders->count() . ' orders',
                'accent' => 'sky',
            ],
            [
                'key' => 'dispatch',
                'title' => 'Dispatch',
                'value' => round((float) $dispatches->sum('delivered_qty'), 3),
                'meta' => $dispatches->count() . ' trips',
                'accent' => 'emerald',
            ],
            [
                'key' => 'accounting',
                'title' => 'Accounting',
                'value' => round((float) $receipts->sum('credit_amount') - (float) $payments->sum('debit_amount'), 2),
                'meta' => 'cash delta',
                'accent' => 'violet',
            ],
            [
                'key' => 'stock',
                'title' => 'Stock',
                'value' => round((float) ($stockOverview['total_quantity'] ?? 0), 2),
                'meta' => (int) ($stockOverview['low_stock_count'] ?? 0) . ' low items',
                'accent' => 'rose',
            ],
        ];
    }

    private function getStockOverview(int $plantId): array
    {
        $quantitySubQuery = Quantity::query()
            ->selectRaw('product_id, SUM(quantity) as stock_qty')
            ->where('plant_id', $plantId)
            ->groupBy('product_id');

        $row = Product::query()
            ->where('mm_products.plant_id', $plantId)
            ->leftJoinSub($quantitySubQuery, 'stock_levels', function ($join) {
                $join->on('stock_levels.product_id', '=', 'mm_products.id');
            })
            ->selectRaw('
                COALESCE(SUM(stock_levels.stock_qty), 0) as total_quantity,
                COALESCE(SUM(COALESCE(stock_levels.stock_qty, 0) * COALESCE(mm_products.purchase_price, 0)), 0) as stock_value,
                COALESCE(SUM(CASE WHEN mm_products.stock_alert > 0 AND COALESCE(stock_levels.stock_qty, 0) <= mm_products.stock_alert THEN 1 ELSE 0 END), 0) as low_stock_count
            ')
            ->first();

        return [
            'total_quantity' => round((float) ($row->total_quantity ?? 0), 2),
            'stock_value' => round((float) ($row->stock_value ?? 0), 2),
            'low_stock_count' => (int) ($row->low_stock_count ?? 0),
        ];
    }

    private function getFinanceTrend(int $plantId, Carbon $start, Carbon $end, ?int $patronId): array
    {
        $useMonthly = $start->diffInDays($end) > 45;
        $bucketFormat = $useMonthly ? '%Y-%m' : '%Y-%m-%d';
        $labelFormat = $useMonthly ? 'M Y' : 'd M';
        $periodStart = $useMonthly ? $start->copy()->startOfMonth() : $start->copy()->startOfDay();
        $periodEnd = $useMonthly ? $end->copy()->startOfMonth() : $end->copy()->startOfDay();
        $interval = $useMonthly ? '1 month' : '1 day';

        $periods = [];
        $labels = [];
        foreach (CarbonPeriod::create($periodStart, $interval, $periodEnd) as $date) {
            $periods[] = $date->format($useMonthly ? 'Y-m' : 'Y-m-d');
            $labels[] = $date->format($labelFormat);
        }

        $salesMap = $this->salesInvoiceQuery($plantId, $start, $end, $patronId)
            ->selectRaw("DATE_FORMAT(invoice_date, '{$bucketFormat}') as period, SUM(total_amount) as total")
            ->groupBy(DB::raw("DATE_FORMAT(invoice_date, '{$bucketFormat}')"))
            ->pluck('total', 'period');

        $purchaseMap = $this->purchaseOrderQuery($plantId, $start, $end, $patronId)
            ->selectRaw("DATE_FORMAT(date_order, '{$bucketFormat}') as period, SUM(amount_total) as total")
            ->groupBy(DB::raw("DATE_FORMAT(date_order, '{$bucketFormat}')"))
            ->pluck('total', 'period');

        $collectionMap = $this->journalLineQuery($plantId, $start, $end, 'RECEIPT', $patronId)
            ->join('mm_journal_entries as journal', 'journal.id', '=', 'mm_journal_entry_lines.journal_entry_id')
            ->selectRaw("DATE_FORMAT(journal.voucher_date, '{$bucketFormat}') as period, SUM(mm_journal_entry_lines.credit_amount) as total")
            ->groupBy(DB::raw("DATE_FORMAT(journal.voucher_date, '{$bucketFormat}')"))
            ->pluck('total', 'period');

        $dispatchMap = $this->dispatchQuery($plantId, $start, $end, $patronId)
            ->selectRaw("DATE_FORMAT(dispatch_time, '{$bucketFormat}') as period, SUM(load_total_amount) as total")
            ->groupBy(DB::raw("DATE_FORMAT(dispatch_time, '{$bucketFormat}')"))
            ->pluck('total', 'period');

        $seriesFor = function ($map) use ($periods) {
            return collect($periods)->map(fn ($period) => round((float) ($map[$period] ?? 0), 2))->values()->all();
        };

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'Sales', 'data' => $seriesFor($salesMap)],
                ['name' => 'Purchase', 'data' => $seriesFor($purchaseMap)],
                ['name' => 'Collections', 'data' => $seriesFor($collectionMap)],
                ['name' => 'Dispatch', 'data' => $seriesFor($dispatchMap)],
            ],
        ];
    }

    private function getDispatchStatusBreakdown(int $plantId, Carbon $start, Carbon $end, ?int $patronId): array
    {
        return $this->dispatchQuery($plantId, $start, $end, $patronId)
            ->selectRaw("COALESCE(NULLIF(dispatch_status, ''), 'Draft') as status_label, COUNT(*) as total")
            ->groupBy('dispatch_status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->status_label,
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function getCustomerLeaderboard(int $plantId, Carbon $start, Carbon $end, ?int $patronId): array
    {
        return $this->dispatchQuery($plantId, $start, $end, $patronId)
            ->leftJoin('mm_patrons as customers', 'customers.id', '=', 'mm_dispatches.customer_id')
            ->selectRaw('
                mm_dispatches.customer_id,
                COALESCE(customers.legal_name, "Walk-in Customer") as customer_name,
                COUNT(mm_dispatches.id) as trips,
                COALESCE(SUM(mm_dispatches.delivered_qty), 0) as quantity,
                COALESCE(SUM(mm_dispatches.load_total_amount), 0) as revenue
            ')
            ->groupBy('mm_dispatches.customer_id', 'customers.legal_name')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'customer' => $row->customer_name,
                'trips' => (int) $row->trips,
                'quantity' => round((float) $row->quantity, 3),
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->values()
            ->all();
    }

    private function getStockSnapshot(int $plantId): array
    {
        $quantitySubQuery = Quantity::query()
            ->selectRaw('product_id, SUM(quantity) as stock_qty')
            ->where('plant_id', $plantId)
            ->groupBy('product_id');

        return Product::query()
            ->where('mm_products.plant_id', $plantId)
            ->leftJoinSub($quantitySubQuery, 'stock_levels', function ($join) {
                $join->on('stock_levels.product_id', '=', 'mm_products.id');
            })
            ->leftJoin('mm_product_units as units', 'units.id', '=', 'mm_products.unit_id')
            ->selectRaw('
                mm_products.id,
                mm_products.title,
                mm_products.stock_alert,
                mm_products.purchase_price,
                COALESCE(stock_levels.stock_qty, 0) as quantity,
                COALESCE(units.unit_name, "Unit") as unit_name
            ')
            ->orderByRaw('CASE WHEN mm_products.stock_alert > 0 AND COALESCE(stock_levels.stock_qty, 0) <= mm_products.stock_alert THEN 0 ELSE 1 END')
            ->orderBy('quantity')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $quantity = (float) $row->quantity;
                $alertLevel = (float) ($row->stock_alert ?? 0);
                $coverage = $alertLevel > 0 ? min(100, round(($quantity / $alertLevel) * 100, 1)) : 100;

                return [
                    'id' => (int) $row->id,
                    'name' => $row->title,
                    'quantity' => round($quantity, 2),
                    'alert_level' => round($alertLevel, 2),
                    'unit' => $row->unit_name,
                    'coverage' => $coverage,
                    'stock_value' => round($quantity * (float) ($row->purchase_price ?? 0), 2),
                    'is_critical' => $alertLevel > 0 && $quantity <= $alertLevel,
                ];
            })
            ->values()
            ->all();
    }

    private function getRecentWorkOrders(int $plantId, ?int $patronId): array
    {
        return WorkOrder::query()
            ->with(['customer', 'mixDesign'])
            ->where('plant_id', $plantId)
            ->when($patronId, fn ($query) => $query->where('customer_id', $patronId))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($workOrder) => [
                'id' => $workOrder->id,
                'number' => $workOrder->full_number,
                'customer' => $workOrder->customer->legal_name ?? 'N/A',
                'grade' => $workOrder->mixDesign->design_name ?? 'N/A',
                'scheduled' => optional($workOrder->scheduled_start)->format('d M, H:i') ?? 'Not scheduled',
                'qty' => (float) $workOrder->total_qty,
                'produced_qty' => (float) $workOrder->produced_qty,
                'status' => WorkOrder::statusLabel((int) $workOrder->status),
            ])
            ->values()
            ->all();
    }

    private function getRecentDispatches(int $plantId, ?int $patronId): array
    {
        return Dispatch::query()
            ->with(['customer', 'truck'])
            ->where('plant_id', $plantId)
            ->when($patronId, fn ($query) => $query->where('customer_id', $patronId))
            ->latest('dispatch_time')
            ->limit(6)
            ->get()
            ->map(fn ($dispatch) => [
                'id' => $dispatch->id,
                'ticket' => trim(($dispatch->prefix ?? '') . ($dispatch->dispatch_no ?? '')),
                'vehicle' => $dispatch->truck->registration ?? 'Unassigned',
                'customer' => $dispatch->customer->legal_name ?? 'N/A',
                'qty' => (float) $dispatch->delivered_qty,
                'amount' => (float) $dispatch->load_total_amount,
                'status' => $dispatch->dispatch_status ?: 'Draft',
                'time' => optional($dispatch->dispatch_time)->format('d M, H:i') ?? 'N/A',
            ])
            ->values()
            ->all();
    }

    private function getRecentPurchaseOrders(int $plantId, ?int $patronId): array
    {
        return PurchaseOrder::query()
            ->with('vendor')
            ->where('plant_id', $plantId)
            ->when($patronId, fn ($query) => $query->where('vendor_id', $patronId))
            ->latest('date_order')
            ->limit(6)
            ->get()
            ->map(fn ($purchaseOrder) => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->po_number,
                'vendor' => $purchaseOrder->vendor->legal_name ?? 'N/A',
                'date' => optional($purchaseOrder->date_order)->format('d M Y') ?? 'N/A',
                'amount' => (float) $purchaseOrder->amount_total,
                'receipt_status' => (int) $purchaseOrder->receipt_status,
                'invoice_status' => (int) $purchaseOrder->invoice_status,
            ])
            ->values()
            ->all();
    }

    private function getRecentActivity(int $plantId, ?int $patronId): array
    {
        return JournalEntryLine::query()
            ->with(['entry', 'ledger', 'partner'])
            ->where('plant_id', $plantId)
            ->when($patronId, fn ($query) => $query->where('partner_id', $patronId))
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($line) => [
                'date' => optional($line->entry?->voucher_date)->format('d M Y') ?? 'N/A',
                'voucher_type' => $line->entry->voucher_type ?? 'N/A',
                'voucher_no' => $line->entry->voucher_number ?? 'N/A',
                'ledger' => $line->ledger->title ?? 'N/A',
                'partner' => $line->partner->legal_name ?? 'General',
                'amount' => (float) ($line->debit_amount > 0 ? $line->debit_amount : $line->credit_amount),
                'dr_cr' => $line->debit_amount > 0 ? 'Dr' : 'Cr',
            ])
            ->values()
            ->all();
    }

    private function emptyPayload(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'metrics' => [],
            'module_cards' => [],
            'finance_trend' => ['labels' => [], 'series' => []],
            'dispatch_status' => [],
            'customer_leaderboard' => [],
            'stock_snapshot' => [],
            'stock_alerts' => [],
            'recent_transactions' => [],
            'work_orders' => [],
            'dispatches' => [],
            'purchase_orders' => [],
        ];
    }
}
