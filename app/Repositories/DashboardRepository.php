<?php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\DashboardAlert;
use App\Models\Plant;
use App\Models\Product;
use App\Models\Quantity;
use App\Models\Patron;
use App\Traits\DashboardFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardRepository
{
    use DashboardFilter;

    private const CBM_TO_CFT = 35.3147;

    private function getDispatchBreakdownResult(array $filters)
    {
        $query = Dispatch::query();
        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->select(
            DB::raw('COALESCE(SUM(load_total_amount), 0) as total_sales'),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'cash' THEN load_total_amount ELSE 0 END), 0) as cash_sales"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'credit' THEN load_total_amount ELSE 0 END), 0) as credit_sales"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'cash' THEN 1 ELSE 0 END), 0) as cash_trips"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'credit' THEN 1 ELSE 0 END), 0) as credit_trips"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'cash' THEN net_weight ELSE 0 END), 0) as cash_mt"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'credit' THEN net_weight ELSE 0 END), 0) as credit_mt"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'cash' THEN delivered_qty ELSE 0 END), 0) as cash_quantity"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(payment_mode) = 'credit' THEN delivered_qty ELSE 0 END), 0) as credit_quantity")
        )->first();
    }

    public function getSalesSummary(array $filters)
    {
        $results = $this->getDispatchBreakdownResult($filters);

        $total = (float) ($results->total_sales ?: 0);
        $cash = (float) ($results->cash_sales ?: 0);
        $credit = (float) ($results->credit_sales ?: 0);

        return [
            'total_sales' => round($total, 2),
            'cash_sales' => [
                'amount' => round($cash, 2),
                'percentage' => $total > 0 ? round(($cash / $total) * 100, 1) : 0
            ],
            'credit_sales' => [
                'amount' => round($credit, 2),
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
            'trips' => (int) ($results->trips ?: 0)
        ];
    }

    public function getDispatchSalesAmounts(array $filters): array
    {
        $results = $this->getDispatchBreakdownResult($filters);

        return [
            'total_dispatch_sales_amount' => round((float) ($results->total_sales ?: 0), 2),
            'cash_sales_amount' => round((float) ($results->cash_sales ?: 0), 2),
            'credit_sales_amount' => round((float) ($results->credit_sales ?: 0), 2),
            'cash_dispatch_count' => (int) ($results->cash_trips ?: 0),
            'credit_dispatch_count' => (int) ($results->credit_trips ?: 0),
            'cash_quantity_mt' => round((float) ($results->cash_mt ?: 0), 2),
            'credit_quantity_mt' => round((float) ($results->credit_mt ?: 0), 2),
            'cash_quantity_cft' => round((float) ($results->cash_quantity ?: 0), 2),
            'credit_quantity_cft' => round((float) ($results->credit_quantity ?: 0), 2),
        ];
    }

    public function getSalesDetailsByPaymentMode(array $filters): array
    {
        $results = $this->getDispatchBreakdownResult($filters);

        $cashCbm = (float) ($results->cash_quantity ?: 0);
        $creditCbm = (float) ($results->credit_quantity ?: 0);

        return [
            'cash_sales' => [
                'sales_amount' => [
                    'amount' => round((float) ($results->cash_sales ?: 0), 2),
                    'unit' => 'amount',
                ],
                'quantity' => [
                    'cbm' => [
                        'qty' => round($cashCbm, 3),
                        'unit' => 'cbm',
                    ],
                    'cft' => [
                        'qty' => round($cashCbm * self::CBM_TO_CFT, 3),
                        'unit' => 'cft',
                    ],
                    'mtr' => [
                        'qty' => round((float) ($results->cash_mt ?: 0), 3),
                        'unit' => 'mtr',
                    ],
                ],
                'trips' => [
                    'dispatch_count' => (int) ($results->cash_trips ?: 0),

                ],
            ],
            'credit_sales' => [
                'sales_amount' => [
                    'amount' => round((float) ($results->credit_sales ?: 0), 2),
                    'unit' => 'amount',
                ],
                'quantity' => [
                    'cbm' => [
                        'qty' => round($creditCbm, 3),
                        'unit' => 'cbm',
                    ],
                    'cft' => [
                        'qty' => round($creditCbm * self::CBM_TO_CFT, 3),
                        'unit' => 'cft',
                    ],
                    'mtr' => [
                        'qty' => round((float) ($results->credit_mt ?: 0), 3),
                        'unit' => 'mtr',
                    ],
                ],
                'trips' => [
                    'dispatch_count' => (int) ($results->credit_trips ?: 0),

                ],
            ],
        ];
    }

    public function getDispatchBatchingSummary(array $filters): array
    {
        $query = Dispatch::query()
            ->leftJoin('mm_batches as batches', function ($join) {
                $join->on('batches.id', '=', 'mm_dispatches.batch_id')
                    ->whereNull('batches.deleted_at');
            });

        $this->applyFilters($query, $filters, 'dispatch_time');

        $results = $query->select(
            DB::raw('COALESCE(SUM(batches.batch_size), 0) as total_batch_size_cbm'),
            DB::raw('COALESCE(SUM(mm_dispatches.net_weight), 0) as total_net_quantity_mtr'),
            DB::raw('COALESCE(SUM(mm_dispatches.delivered_qty), 0) as total_net_quantity_cbm'),
            DB::raw('COUNT(mm_dispatches.id) as total_batching_count')
        )->first();

        $totalNetQuantityCbm = (float) ($results->total_net_quantity_cbm ?: 0);

        return [
            'total_dispatch_batch_size' => [
                'qty' => round((float) ($results->total_batch_size_cbm ?: 0), 3),
                'unit' => 'cbm',
            ],
            'total_dispatch_net_quantity' => [
                'mtr' => [
                    'qty' => round((float) ($results->total_net_quantity_mtr ?: 0), 3),
                    'unit' => 'mtr',
                ],
                'cft' => [
                    'qty' => round($totalNetQuantityCbm * self::CBM_TO_CFT, 3),
                    'unit' => 'cft',
                ],
            ],
            'total_batching_count' => [
                'qty' => (int) ($results->total_batching_count ?: 0),
                'unit' => 'count',
            ],
        ];
    }

    public function getDispatchDetailsByTruck(array $filters)
    {
        $query = Dispatch::query()
            ->leftJoin('mm_batches as batches', function ($join) {
                $join->on('batches.id', '=', 'mm_dispatches.batch_id')
                    ->whereNull('batches.deleted_at');
            })
            ->leftJoin('mm_machines as truck', function ($join) {
                $join->on('truck.id', '=', 'mm_dispatches.truck_id')
                    ->whereNull('truck.deleted_at');
            })
            ->select(
                'mm_dispatches.truck_id',
                'truck.registration as truck_registration',
                DB::raw('COUNT(mm_dispatches.id) as total_dispatch_count'),
                DB::raw('COALESCE(SUM(batches.batch_size), 0) as total_batch_size'),
                DB::raw('COALESCE(SUM(mm_dispatches.delivered_qty), 0) as total_qty_cbm'),
                DB::raw('COALESCE(SUM(mm_dispatches.net_weight), 0) as total_qty_mtr')
            )
            ->groupBy('mm_dispatches.truck_id', 'truck.registration')
            ->orderByDesc('total_dispatch_count');

        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->get()->map(function ($row) {
            return [
                'truck_id' => $row->truck_id ? (int) $row->truck_id : null,
                'truck_registration' => $row->truck_registration,
                'total_dispatch_count' => [
                    'total_count' => (int) $row->total_dispatch_count,
                    'unit' => 'count',
                ],
                'total_batch_size' => [
                    'qty' => round((float) $row->total_batch_size, 3),
                    'unit' => 'cbm',
                ],
                'total_qty' => [
                    'cft' => [
                        'qty' => round(((float) $row->total_qty_cbm) * self::CBM_TO_CFT, 3),
                        'unit' => 'cft',
                    ],
                    'mtr' => [
                        'qty' => round((float) $row->total_qty_mtr, 3),
                        'unit' => 'mtr',
                    ],
                ],
            ];
        })->values();
    }

    public function getDispatchDetails(array $filters)
    {
        $query = Dispatch::query()
            ->with(['customer', 'driver', 'truck', 'mixDesign', 'unloadSite', 'uom'])
            ->orderBy('dispatch_time', 'desc');

        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'dispatch_no' => $d->dispatch_no,
                'dispatch_time' => $d->dispatch_time?->toDateTimeString(),
                'dispatch_status' => $d->dispatch_status,
                'payment_mode' => $d->payment_mode,
                'customer' => [
                    'id' => $d->customer_id,
                    'name' => $d->customer?->legal_name,
                ],
                'unload_site' => [
                    'id' => $d->unload_site_id,
                    'name' => $d->unloadSite?->name,
                ],
                'mix_design' => [
                    'id' => $d->mixdesign_id,
                    'name' => $d->mixDesign?->design_name,
                    'code' => $d->mixDesign?->design_code,
                ],
                'delivered_qty' => [
                    'qty' => round((float) $d->delivered_qty, 3),
                    'unit' => $d->uom?->unit_code ?: ($d->mixDesign?->uom?->unit_code ?: 'm³'),
                ],
                'truck' => [
                    'id' => $d->truck_id,
                    'registration' => $d->truck?->registration ?: $d->truck?->name,
                ],
                'driver' => [
                    'id' => $d->driver_id,
                    'name' => $d->driver ? trim($d->driver->first_name . ' ' . $d->driver->last_name) : null,
                ],
                'financials' => [
                    'load_rate' => round((float) $d->load_rate, 2),
                    'load_untax_amount' => round((float) $d->load_untax_amount, 2),
                    'load_tax_amount' => round((float) $d->load_tax_amount, 2),
                    'load_total_amount' => round((float) $d->load_total_amount, 2),
                ]
            ];
        })->values();
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

    public function getTopMixDesignsFromBatches(array $filters)
    {
        $hasConcreteGrades = Schema::hasTable('mm_concrete_grades');

        $query = Batch::query()
            ->join('mm_sales_orders as wo', 'wo.id', '=', 'mm_batches.sales_order_id')
            ->join('mm_mix_designs as md', 'md.id', '=', 'wo.mix_design_id')
            ->when($hasConcreteGrades, function ($query) {
                $query->leftJoin('mm_concrete_grades as cg', function ($join) {
                    $join->on('cg.plant_id', '=', 'md.plant_id')
                        ->on('cg.name', '=', 'md.design_type')
                        ->whereNull('cg.deleted_at');
                });
            })
            ->whereNull('wo.deleted_at')
            ->whereNull('md.deleted_at')
            ->select(
                'md.id as mix_design_id',
                'md.design_name',
                'md.design_code',
                DB::raw($hasConcreteGrades ? 'COALESCE(cg.name, md.design_type) as grade' : 'md.design_type as grade'),
                DB::raw('COALESCE(SUM(mm_batches.batch_size), 0) as total_batch_size'),
                DB::raw('COUNT(mm_batches.id) as total_batch_count')
            )
            ->groupBy(
                'md.id',
                'md.design_name',
                'md.design_code',
                'md.design_type',
                ...($hasConcreteGrades ? ['cg.name'] : [])
            )
            ->orderByDesc('total_batch_size')
            ->limit(5);

        $this->applyFilters($query, $filters, 'start_time');

        return $query->get()->map(function ($row) {
            return [
                'mix_design_id' => (int) $row->mix_design_id,
                'design_name' => $row->design_name,
                'design_code' => $row->design_code,
                'grade' => $row->grade,
                'total_batch_size' => [
                    'qty' => round((float) $row->total_batch_size, 3),
                    'unit' => 'cbm',
                ],
                'total_batch_count' => [
                    'qty' => (int) $row->total_batch_count,
                    'unit' => 'count',
                ],
            ];
        })->values();
    }

    public function getStockDetails(array $filters)
    {
        $query = Product::query()
            ->leftJoin('mm_quantity as qty', function ($join) {
                $join->on('qty.product_id', '=', 'mm_products.id')
                    ->whereNull('qty.deleted_at');
            })
            ->leftJoin('mm_product_units as unit', 'unit.id', '=', 'mm_products.unit_id')
            ->whereNull('mm_products.deleted_at')
            ->select(
                'mm_products.id as product_id',
                'mm_products.title as product_name',
                'mm_products.code as product_code',
                'mm_products.stock_alert',
                'unit.unit_name',
                'unit.unit_code',
                DB::raw('COALESCE(SUM(qty.quantity), 0) as current_stock')
            )
            ->groupBy(
                'mm_products.id',
                'mm_products.title',
                'mm_products.code',
                'mm_products.stock_alert',
                'unit.unit_name',
                'unit.unit_code'
            )
            ->orderBy('mm_products.title');

        if (isset($filters['plant_id'])) {
            $query->where('mm_products.plant_id', $filters['plant_id'])
                ->where(function ($subQuery) use ($filters) {
                    $subQuery->whereNull('qty.plant_id')
                        ->orWhere('qty.plant_id', $filters['plant_id']);
                });
        }

        return $query->get()->map(function ($row) {
            $currentStock = (float) $row->current_stock;
            $stockAlert = (float) ($row->stock_alert ?? 0);

            return [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'product_code' => $row->product_code,
                'current_stock' => [
                    'qty' => round($currentStock, 2),
                    'unit' => $row->unit_code ?: ($row->unit_name ?: 'unit'),
                ],
                'stock_alert' => [
                    'qty' => round($stockAlert, 2),
                    'unit' => $row->unit_code ?: ($row->unit_name ?: 'unit'),
                ],
                'stock_status' => [
                    'in_stock' => $currentStock > 0,
                    'below_alert' => $stockAlert > 0 ? $currentStock <= $stockAlert : false,
                ],
            ];
        })->values();
    }

    public function getTripsDetails(array $filters)
    {
        $query = Dispatch::with('truck')
            ->select('truck_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(delivered_qty) as m3'))
            ->groupBy('truck_id');

        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->get()->map(function ($d) {
            return [
                'vehicle_no' => $d->truck->registration ?? 'N/A',
                'count' => $d->count,
                'm3' => round($d->m3, 2)
            ];
        });
    }

    public function getCustomerDetails(array $filters)
    {
        $hasConcreteGrades = Schema::hasTable('mm_concrete_grades');
        $gradeExpression = $hasConcreteGrades
            ? 'COALESCE(cg.name, md.design_type)'
            : 'md.design_type';

        $summaryQuery = Dispatch::query()
            ->join('mm_patrons as customer', function ($join) {
                $join->on('customer.id', '=', 'mm_dispatches.customer_id')
                    ->whereNull('customer.deleted_at');
            })
            ->join('mm_mix_designs as md', function ($join) {
                $join->on('md.id', '=', 'mm_dispatches.mixdesign_id')
                    ->whereNull('md.deleted_at');
            })
            ->when($hasConcreteGrades, function ($query) {
                $query->leftJoin('mm_concrete_grades as cg', function ($join) {
                    $join->on('cg.plant_id', '=', 'md.plant_id')
                        ->on('cg.name', '=', 'md.design_type')
                        ->whereNull('cg.deleted_at');
                });
            })
            ->select(
                'customer.id as customer_id',
                'customer.legal_name as customer_name',
                'md.id as mix_design_id',
                'md.design_name',
                DB::raw($gradeExpression . ' as grade'),
                DB::raw('COUNT(DISTINCT mm_dispatches.id) as total_dispatch_count')
            )
            ->groupBy(
                'customer.id',
                'customer.legal_name',
                'md.id',
                'md.design_name',
                'md.design_type',
                ...($hasConcreteGrades ? ['cg.name'] : [])
            )
            ->orderBy('customer.legal_name')
            ->orderBy('md.design_name');

        $this->applyFilters($summaryQuery, $filters, 'dispatch_time');

        $summaryRows = $summaryQuery->get();

        if ($summaryRows->isEmpty()) {
            return collect();
        }

        $materialsQuery = Dispatch::query()
            ->join('mm_patrons as customer', function ($join) {
                $join->on('customer.id', '=', 'mm_dispatches.customer_id')
                    ->whereNull('customer.deleted_at');
            })
            ->join('mm_mix_designs as md', function ($join) {
                $join->on('md.id', '=', 'mm_dispatches.mixdesign_id')
                    ->whereNull('md.deleted_at');
            })
            ->leftJoin('mm_batches as batches', function ($join) {
                $join->on('batches.id', '=', 'mm_dispatches.batch_id')
                    ->whereNull('batches.deleted_at');
            })
            ->leftJoin('mm_batch_materials as bm', function ($join) {
                $join->on('bm.batch_id', '=', 'batches.id')
                    ->whereNull('bm.deleted_at');
            })
            ->leftJoin('mm_product_units as uom', function ($join) {
                $join->on('uom.id', '=', 'bm.uom_id')
                    ->whereNull('uom.deleted_at');
            })
            ->when($hasConcreteGrades, function ($query) {
                $query->leftJoin('mm_concrete_grades as cg', function ($join) {
                    $join->on('cg.plant_id', '=', 'md.plant_id')
                        ->on('cg.name', '=', 'md.design_type')
                        ->whereNull('cg.deleted_at');
                });
            })
            ->whereNotNull('bm.id')
            ->select(
                'customer.id as customer_id',
                'md.id as mix_design_id',
                DB::raw($gradeExpression . ' as grade'),
                'bm.material_name',
                'uom.unit_name',
                'uom.unit_code',
                DB::raw('COALESCE(SUM(bm.target_qty), 0) as total_target_qty'),
                DB::raw('COALESCE(SUM(bm.actual_qty), 0) as total_actual_qty'),
                DB::raw('COALESCE(SUM(bm.deviation_quantity), 0) as total_deviation_qty')
            )
            ->groupBy(
                'customer.id',
                'md.id',
                'md.design_type',
                'bm.material_name',
                'uom.unit_name',
                'uom.unit_code',
                ...($hasConcreteGrades ? ['cg.name'] : [])
            )
            ->orderBy('bm.material_name');

        $this->applyFilters($materialsQuery, $filters, 'dispatch_time');

        $materialsByGroup = $materialsQuery->get()
            ->groupBy(function ($row) {
                return implode('|', [
                    $row->customer_id,
                    $row->mix_design_id,
                    (string) $row->grade,
                ]);
            });

        return $summaryRows->map(function ($row) use ($materialsByGroup) {
            $groupKey = implode('|', [
                $row->customer_id,
                $row->mix_design_id,
                (string) $row->grade,
            ]);

            $materials = ($materialsByGroup->get($groupKey) ?? collect())
                ->map(function ($materialRow) {
                    $unit = $materialRow->unit_code ?: ($materialRow->unit_name ?: 'unit');

                    return [
                        'material_name' => $materialRow->material_name,
                        'target_qty' => [
                            'qty' => round((float) $materialRow->total_target_qty, 3),
                            'unit' => $unit,
                        ],
                        'actual_qty' => [
                            'qty' => round((float) $materialRow->total_actual_qty, 3),
                            'unit' => $unit,
                        ],
                        'deviation_qty' => [
                            'qty' => round((float) $materialRow->total_deviation_qty, 3),
                            'unit' => $unit,
                        ],
                    ];
                })
                ->values();

            return [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => $row->customer_name,
                'grade' => $row->grade,
                'mix_design' => [
                    'id' => (int) $row->mix_design_id,
                    'design_name' => $row->design_name,
                ],
                'total_dispatch_count' => [
                    'qty' => (int) $row->total_dispatch_count,
                    'unit' => 'count',
                ],
                'material_consumption' => $materials,
            ];
        })->values();
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
        $user = auth()->user();
        if ($user && !$user->isSystemAdmin()) {
            $authorizedPlantIds = $user->entityUsers()
                ->whereNotNull('plant_id')
                ->pluck('plant_id')
                ->unique()
                ->toArray();
            return Plant::whereIn('id', $authorizedPlantIds)->select('id', 'name as plant_name')->get();
        }
        return Plant::select('id', 'name as plant_name')->get();
    }
}
