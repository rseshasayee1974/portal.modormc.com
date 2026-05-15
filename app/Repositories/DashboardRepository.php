<?php

namespace App\Repositories;

use App\Models\Dispatch;
use App\Models\DashboardAlert;
use App\Models\Plant;
use App\Models\Product;
use App\Models\Quantity;
use App\Models\Patron;
use App\Traits\DashboardFilter;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    use DashboardFilter;

    public function getSalesSummary(array $filters)
    {
        $query = Dispatch::query();
        $this->applyFilters($query, $filters, 'dispatch_time');

        $results = $query->select(
            DB::raw('SUM(load_total_amount) as total_sales'),
            DB::raw("SUM(CASE WHEN payment_mode = 'Cash' THEN load_total_amount ELSE 0 END) as cash_sales"),
            DB::raw("SUM(CASE WHEN payment_mode = 'Credit' THEN load_total_amount ELSE 0 END) as credit_sales")
        )->first();

        $total = $results->total_sales ?: 0;
        $cash = $results->cash_sales ?: 0;
        $credit = $results->credit_sales ?: 0;

        return [
            'total_sales' => $total,
            'cash_sales' => [
                'amount' => $cash,
                'percentage' => $total > 0 ? round(($cash / $total) * 100, 1) : 0
            ],
            'credit_sales' => [
                'amount' => $credit,
                'percentage' => $total > 0 ? round(($credit / $total) * 100, 1) : 0
            ]
        ];
    }

    public function getSalesStats(array $filters)
    {
        $query = Dispatch::query();
        $this->applyFilters($query, $filters, 'dispatch_time');

        $results = $query->select(
            DB::raw('SUM(net_weight) as mt'),
            DB::raw('SUM(delivered_qty) as unit_cft'),
            DB::raw('COUNT(*) as trips')
        )->first();

        return [
            'mt' => round($results->mt ?: 0, 2),
            'unit_cft' => round($results->unit_cft ?: 0, 2),
            'trips' => (int)($results->trips ?: 0)
        ];
    }

    public function getTopProducts(array $filters)
    {
        $query = DB::table('mm_dispatches as d')
            ->join('mm_mix_designs as m', 'd.mixdesign_id', '=', 'm.id')
            ->select(
                'm.design_name as product_name',
                DB::raw('SUM(d.net_weight) as tonnage'),
                DB::raw('COUNT(*) as trips')
            )
            ->groupBy('m.design_name')
            ->orderBy('tonnage', 'desc')
            ->limit(5);

        if (isset($filters['plant_id'])) {
            $query->where('d.plant_id', $filters['plant_id']);
        }
        
        // Date filtering
        if (isset($filters['type'])) {
            // ... manual date filter implementation for raw query ...
        }

        return $query->get();
    }

    public function getStockDetails(array $filters)
    {
        $query = Quantity::with('product')
            ->whereHas('product', function($q) {
                $q->whereNull('deleted_at');
            });

        if (isset($filters['plant_id'])) {
            $query->where('plant_id', $filters['plant_id']);
        }

        return $query->get()->map(function($q) {
            return [
                'product' => $q->product->title ?? 'Unknown',
                'quantity' => $q->quantity,
                'stock_status' => $q->quantity > 0
            ];
        });
    }

    public function getTripsDetails(array $filters)
    {
        $query = Dispatch::with('truck')
            ->select('truck_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(delivered_qty) as m3'))
            ->groupBy('truck_id');

        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->get()->map(function($d) {
            return [
                'vehicle_no' => $d->truck->registration ?? 'N/A',
                'count' => $d->count,
                'm3' => round($d->m3, 2)
            ];
        });
    }

    public function getCustomerDetails(array $filters)
    {
        // Placeholder for customer details as per design
        return Patron::limit(10)->get()->map(function($p) {
            return [
                'customer_name' => $p->legal_name,
                'grade' => 'M28', // Example data
                'mix' => 'M25GSP', // Example data
                'materials' => [
                    ['name' => 'P-Sand', 'percentage' => 50],
                    ['name' => 'M-Sand', 'percentage' => 30]
                ]
            ];
        });
    }

    public function getAlerts(array $filters)
    {
        $query = DashboardAlert::query();
        if (isset($filters['plant_id'])) {
            $query->where('plant_id', $filters['plant_id']);
        }
        return $query->orderBy('created_at', 'desc')->limit(10)->get();
    }

    public function getPlants()
    {
        return Plant::select('id', 'name as plant_name')->get();
    }
}
