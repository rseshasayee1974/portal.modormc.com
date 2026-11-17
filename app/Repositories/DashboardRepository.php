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
        $hasBatches = Schema::hasTable('mm_batches');
        $query = Dispatch::withoutGlobalScope('plant_id')
            ->when($hasBatches, function ($q) {
                $q->leftJoin('mm_batches as batches', function ($join) {
                    $join->on('batches.id', '=', 'mm_dispatches.batch_id');
                    if (Schema::hasColumn('mm_batches', 'deleted_at')) {
                        $join->whereNull('batches.deleted_at');
                    }
                });
            });

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $query->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $this->applyFilters($query, $filters, 'dispatch_time');

        $deliveredQtyExpr = $hasBatches
            ? 'COALESCE(mm_dispatches.delivered_qty, batches.batch_size, 0)'
            : 'COALESCE(mm_dispatches.delivered_qty, 0)';

        return $query->select(
            DB::raw('COALESCE(SUM(mm_dispatches.load_total_amount), 0) as total_sales'),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'cash' THEN mm_dispatches.load_total_amount ELSE 0 END), 0) as cash_sales"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'credit' THEN mm_dispatches.load_total_amount ELSE 0 END), 0) as credit_sales"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'cash' THEN 1 ELSE 0 END), 0) as cash_trips"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'credit' THEN 1 ELSE 0 END), 0) as credit_trips"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'cash' THEN mm_dispatches.net_weight ELSE 0 END), 0) as cash_mt"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'credit' THEN mm_dispatches.net_weight ELSE 0 END), 0) as credit_mt"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'cash' THEN {$deliveredQtyExpr} ELSE 0 END), 0) as cash_quantity"),
            DB::raw("COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(mm_dispatches.payment_mode, ''))) = 'credit' THEN {$deliveredQtyExpr} ELSE 0 END), 0) as credit_quantity")
        )->first();
    }

    public function getSalesSummary(array $filters)
    {
        $results = $this->getDispatchBreakdownResult($filters);

        $total = (float) ($results?->total_sales ?: 0);
        $cash = (float) ($results?->cash_sales ?: 0);
        $credit = (float) ($results?->credit_sales ?: 0);

        $cashPct = $total > 0 ? round(($cash / $total) * 100, 1) : 0;
        $creditPct = $total > 0 ? round(($credit / $total) * 100, 1) : 0;

        return [
            'total_sales' => round($total, 2),
            'cash_sales' => [
                'amount' => round($cash, 2),
                'percentage' => $cashPct,
                '%' => $cashPct,
            ],
            'credit_sales' => [
                'amount' => round($credit, 2),
                'percentage' => $creditPct,
                '%' => $creditPct,
            ]
        ];
    }

    public function getSalesStats(array $filters)
    {
        $hasBatches = Schema::hasTable('mm_batches');
        $query = Dispatch::withoutGlobalScope('plant_id')
            ->when($hasBatches, function ($q) {
                $q->leftJoin('mm_batches as batches', function ($join) {
                    $join->on('batches.id', '=', 'mm_dispatches.batch_id');
                    if (Schema::hasColumn('mm_batches', 'deleted_at')) {
                        $join->whereNull('batches.deleted_at');
                    }
                });
            });

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $query->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $this->applyFilters($query, $filters, 'dispatch_time');

        $deliveredQtyExpr = $hasBatches
            ? 'COALESCE(mm_dispatches.delivered_qty, batches.batch_size, 0)'
            : 'COALESCE(mm_dispatches.delivered_qty, 0)';

        $results = $query->select(
            DB::raw('COALESCE(SUM(mm_dispatches.net_weight), 0) as mt'),
            DB::raw("COALESCE(SUM({$deliveredQtyExpr}), 0) as unit_cft"),
            DB::raw('COUNT(mm_dispatches.id) as trips')
        )->first();

        return [
            'mt' => round((float) ($results?->mt ?: 0), 2),
            'unit_cft' => round((float) ($results?->unit_cft ?: 0), 2),
            'trips' => (int) ($results?->trips ?: 0)
        ];
    }

    public function getDispatchSalesAmounts(array $filters): array
    {
        $results = $this->getDispatchBreakdownResult($filters);

        return [
            'total_dispatch_sales_amount' => round((float) ($results?->total_sales ?: 0), 2),
            'cash_sales_amount' => round((float) ($results?->cash_sales ?: 0), 2),
            'credit_sales_amount' => round((float) ($results?->credit_sales ?: 0), 2),
            'cash_dispatch_count' => (int) ($results?->cash_trips ?: 0),
            'credit_dispatch_count' => (int) ($results?->credit_trips ?: 0),
            'cash_quantity_mt' => round((float) ($results?->cash_mt ?: 0), 2),
            'credit_quantity_mt' => round((float) ($results?->credit_mt ?: 0), 2),
            'cash_quantity_cft' => round((float) ($results?->cash_quantity ?: 0), 2),
            'credit_quantity_cft' => round((float) ($results?->credit_quantity ?: 0), 2),
        ];
    }

    public function getSalesDetailsByPaymentMode(array $filters): array
    {
        $results = $this->getDispatchBreakdownResult($filters);

        $cashCbm = (float) ($results?->cash_quantity ?: 0);
        $creditCbm = (float) ($results?->credit_quantity ?: 0);

        return [
            'cash_sales' => [
                'sales_amount' => [
                    'amount' => round((float) ($results?->cash_sales ?: 0), 2),
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
                        'qty' => round((float) ($results?->cash_mt ?: 0), 3),
                        'unit' => 'mtr',
                    ],
                ],
                'trips' => [
                    'dispatch_count' => (int) ($results?->cash_trips ?: 0),
                ],
            ],
            'credit_sales' => [
                'sales_amount' => [
                    'amount' => round((float) ($results?->credit_sales ?: 0), 2),
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
                        'qty' => round((float) ($results?->credit_mt ?: 0), 3),
                        'unit' => 'mtr',
                    ],
                ],
                'trips' => [
                    'dispatch_count' => (int) ($results?->credit_trips ?: 0),
                ],
            ],
        ];
    }

    public function getDispatchBatchingSummary(array $filters): array
    {
        $hasBatches = Schema::hasTable('mm_batches');
        $query = Dispatch::withoutGlobalScope('plant_id')
            ->when($hasBatches, function ($q) {
                $q->leftJoin('mm_batches as batches', function ($join) {
                    $join->on('batches.id', '=', 'mm_dispatches.batch_id');
                    if (Schema::hasColumn('mm_batches', 'deleted_at')) {
                        $join->whereNull('batches.deleted_at');
                    }
                });
            });

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $query->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $this->applyFilters($query, $filters, 'dispatch_time');

        $deliveredQtyExpr = $hasBatches
            ? 'COALESCE(mm_dispatches.delivered_qty, batches.batch_size, 0)'
            : 'COALESCE(mm_dispatches.delivered_qty, 0)';

        $batchSizeExpr = $hasBatches
            ? 'COALESCE(SUM(batches.batch_size), 0)'
            : '0';

        $results = $query->select(
            DB::raw("{$batchSizeExpr} as total_batch_size_cbm"),
            DB::raw('COALESCE(SUM(mm_dispatches.net_weight), 0) as total_net_quantity_mtr'),
            DB::raw("COALESCE(SUM({$deliveredQtyExpr}), 0) as total_net_quantity_cbm"),
            DB::raw('COUNT(mm_dispatches.id) as total_batching_count')
        )->first();

        $totalNetQuantityCbm = (float) ($results?->total_net_quantity_cbm ?: 0);

        return [
            'total_dispatch_batch_size' => [
                'qty' => round((float) ($results?->total_batch_size_cbm ?: 0), 3),
                'unit' => 'cbm',
            ],
            'total_dispatch_net_quantity' => [
                'mtr' => [
                    'qty' => round((float) ($results?->total_net_quantity_mtr ?: 0), 3),
                    'unit' => 'mtr',
                ],
                'cft' => [
                    'qty' => round($totalNetQuantityCbm * self::CBM_TO_CFT, 3),
                    'unit' => 'cft',
                ],
            ],
            'total_batching_count' => [
                'qty' => (int) ($results?->total_batching_count ?: 0),
                'unit' => 'count',
            ],
        ];
    }

    public function getDispatchDetailsByTruck(array $filters)
    {
        $hasBatches = Schema::hasTable('mm_batches');
        $hasMachines = Schema::hasTable('mm_machines');

        $query = Dispatch::withoutGlobalScope('plant_id')
            ->when($hasBatches, function ($q) {
                $q->leftJoin('mm_batches as batches', 'batches.id', '=', 'mm_dispatches.batch_id');
            })
            ->when($hasMachines, function ($q) {
                $q->leftJoin('mm_machines as truck', function ($join) {
                    $join->on('truck.id', '=', 'mm_dispatches.truck_id');
                    if (Schema::hasColumn('mm_machines', 'deleted_at')) {
                        $join->whereNull('truck.deleted_at');
                    }
                });
            });

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $query->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $truckRegExpr = $hasMachines
            ? "COALESCE(truck.registration, 'N/A')"
            : "'N/A'";

        $batchSizeExpr = $hasBatches
            ? (Schema::hasColumn('mm_batches', 'deleted_at')
                ? 'COALESCE(SUM(CASE WHEN batches.deleted_at IS NULL THEN batches.batch_size END), 0)'
                : 'COALESCE(SUM(batches.batch_size), 0)')
            : '0';

        $totalQtyCbmExpr = $hasBatches
            ? 'COALESCE(SUM(COALESCE(mm_dispatches.delivered_qty, batches.batch_size, 0)), 0)'
            : 'COALESCE(SUM(COALESCE(mm_dispatches.delivered_qty, 0)), 0)';

        $groupBys = ['mm_dispatches.truck_id'];
        if ($hasMachines) {
            $groupBys[] = 'truck.registration';
        }

        $query->select(
            'mm_dispatches.truck_id',
            DB::raw("{$truckRegExpr} as truck_registration"),
            DB::raw('COUNT(mm_dispatches.id) as total_dispatch_count'),
            DB::raw("{$batchSizeExpr} as total_batch_size"),
            DB::raw("{$totalQtyCbmExpr} as total_qty_cbm"),
            DB::raw('COALESCE(SUM(mm_dispatches.net_weight), 0) as total_qty_mtr')
        )
        ->groupBy($groupBys)
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
        $hasSalesOrders = Schema::hasTable('mm_sales_orders');
        $hasMixDesigns = Schema::hasTable('mm_mix_designs');
        $hasMachines = Schema::hasTable('mm_machines');
        $hasPersonnels = Schema::hasTable('mm_personnels');
        $hasPatrons = Schema::hasTable('mm_patrons');
        $hasSites = Schema::hasTable('mm_sites');
        $hasUnits = Schema::hasTable('mm_product_units');
        $hasBatches = Schema::hasTable('mm_batches');

        $with = [];
        if ($hasPatrons) $with[] = 'customer:id,legal_name';
        if ($hasSalesOrders) {
            $with[] = 'salesOrder:id,customer_id,mix_design_id,site_id';
            if ($hasPatrons) $with[] = 'salesOrder.customer:id,legal_name';
            if ($hasMixDesigns) {
                $with[] = 'salesOrder.mixDesign:id,design_name,design_code,unit_id,concrete_grade_id';
                if ($hasUnits) $with[] = 'salesOrder.mixDesign.unit:id,unit_code,unit_name';
            }
            if ($hasSites) $with[] = 'salesOrder.site:id,name';
        }
        if ($hasPersonnels) $with[] = 'driver:id,first_name,last_name';
        if ($hasMachines) $with[] = 'truck:id,registration';
        if ($hasMixDesigns) {
            $with[] = 'mixDesign:id,design_name,design_code,unit_id,concrete_grade_id';
            if ($hasUnits) $with[] = 'mixDesign.unit:id,unit_code,unit_name';
        }
        if ($hasSites) $with[] = 'unloadSite:id,name';
        if ($hasUnits) $with[] = 'uom:id,unit_code,unit_name';
        if ($hasBatches) $with[] = 'batch:id,batch_size';

        $query = Dispatch::withoutGlobalScope('plant_id')
            ->with($with)
            ->orderBy('dispatch_time', 'desc');

        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->get()->map(function ($d) {
            $customer = $d->customer ?? $d->salesOrder?->customer;
            $unloadSite = $d->unloadSite ?? $d->salesOrder?->site;
            $mixDesign = $d->mixDesign ?? $d->salesOrder?->mixDesign;
            $uom = $d->uom ?? $mixDesign?->unit;

            $driverName = null;
            if ($d->driver) {
                $driverName = trim(($d->driver->first_name ?? '') . ' ' . ($d->driver->last_name ?? '')) ?: null;
            }

            return [
                'id' => $d->id,
                'dispatch_no' => $d->dispatch_no,
                'dispatch_time' => $d->dispatch_time?->toDateTimeString(),
                'dispatch_status' => $d->dispatch_status,
                'payment_mode' => $d->payment_mode,
                'customer' => [
                    'id' => $d->customer_id ?: $customer?->id,
                    'name' => $customer?->legal_name,
                ],
                'unload_site' => [
                    'id' => $d->unload_site_id ?: $unloadSite?->id,
                    'name' => $unloadSite?->name,
                ],
                'mix_design' => [
                    'id' => $d->mixdesign_id ?: $mixDesign?->id,
                    'name' => $mixDesign?->design_name,
                    'code' => $mixDesign?->design_code,
                ],
                'delivered_qty' => [
                    'qty' => round((float) $d->delivered_qty, 3),
                    'unit' => $uom?->unit_code ?: ($uom?->unit_name ?: 'm³'),
                ],
                'truck' => [
                    'id' => $d->truck_id,
                    'registration' => $d->truck?->registration ?: 'N/A',
                ],
                'driver' => [
                    'id' => $d->driver_id,
                    'name' => $driverName,
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
        $hasDispatchSoftDeletes = Schema::hasColumn('mm_dispatches', 'deleted_at');
        $hasMixSoftDeletes = Schema::hasColumn('mm_mix_designs', 'deleted_at');
        $hasDispatchStatus = Schema::hasColumn('mm_dispatches', 'dispatch_status');

        $query = DB::table('mm_dispatches as d')
            ->join('mm_mix_designs as m', function ($join) use ($hasMixSoftDeletes) {
                $join->on('d.mixdesign_id', '=', 'm.id');
                if ($hasMixSoftDeletes) {
                    $join->whereNull('m.deleted_at');
                }
            })
            ->when($hasDispatchSoftDeletes, function ($q) {
                $q->whereNull('d.deleted_at');
            })
            ->when($hasDispatchStatus, function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('d.dispatch_status')
                        ->orWhere('d.dispatch_status', '!=', 'Cancelled');
                });
            })
            ->select(
                'm.design_name as product_name',
                DB::raw('COALESCE(SUM(d.net_weight), 0) as tonnage'),
                DB::raw('COUNT(d.id) as trips')
            )
            ->groupBy('m.id', 'm.design_name')
            ->orderByDesc('tonnage')
            ->limit(5);

        app(\App\Services\DashboardPlantAccess::class)->scope($query, 'd.plant_id', $filters);

        if (Schema::hasColumn('mm_dispatches', 'dispatch_time')) {
            $this->applyDateFiltersToQuery($query, $filters, 'd.dispatch_time');
        }

        return $query->get()->map(function ($row) {
            return (object) [
                'product_name' => $row->product_name,
                'tonnage' => round((float) $row->tonnage, 2),
                'trips' => (int) $row->trips,
            ];
        });
    }

    public function getTopMixDesignsFromBatches(array $filters)
    {
        $hasConcreteGrades = Schema::hasTable('mm_concrete_grades');
        $hasSalesOrders = Schema::hasTable('mm_sales_orders');
        $hasMixDesigns = Schema::hasTable('mm_mix_designs');
        $hasBatches = Schema::hasTable('mm_batches');
        $hasDispatches = Schema::hasTable('mm_dispatches');

        if (!$hasMixDesigns || (!$hasSalesOrders && !$hasDispatches)) {
            return collect();
        }

        $hasConcreteGradeId = Schema::hasColumn('mm_mix_designs', 'concrete_grade_id');

        $gradeExpr = $hasConcreteGrades
            ? 'COALESCE(cg.name, md.design_type)'
            : 'md.design_type';

        $groupBys = ['md.id', 'md.design_name', 'md.design_code', 'md.design_type'];
        if ($hasConcreteGrades) {
            $groupBys[] = 'cg.name';
        }

        $results = collect();

        if ($hasBatches) {
            $query = Batch::withoutGlobalScope('plant_id')
                ->when($hasSalesOrders, function ($q) {
                    $q->leftJoin('mm_sales_orders as wo', function ($join) {
                        $join->on('wo.id', '=', 'mm_batches.sales_order_id');
                        if (Schema::hasColumn('mm_sales_orders', 'deleted_at')) {
                            $join->whereNull('wo.deleted_at');
                        }
                    });
                })
                ->when($hasDispatches, function ($q) {
                    $dispSub = DB::table('mm_dispatches')
                        ->whereNotNull('batch_id')
                        ->whereNotNull('mixdesign_id')
                        ->when(Schema::hasColumn('mm_dispatches', 'deleted_at'), fn($sq) => $sq->whereNull('deleted_at'))
                        ->select('batch_id', DB::raw('MAX(mixdesign_id) as mixdesign_id'))
                        ->groupBy('batch_id');

                    $q->leftJoinSub($dispSub, 'd', 'd.batch_id', '=', 'mm_batches.id');
                })
                ->join('mm_mix_designs as md', function ($join) use ($hasSalesOrders, $hasDispatches) {
                    if ($hasSalesOrders && $hasDispatches) {
                        $join->on('md.id', '=', DB::raw('COALESCE(wo.mix_design_id, d.mixdesign_id)'));
                    } elseif ($hasSalesOrders) {
                        $join->on('md.id', '=', 'wo.mix_design_id');
                    } elseif ($hasDispatches) {
                        $join->on('md.id', '=', 'd.mixdesign_id');
                    }
                    if (Schema::hasColumn('mm_mix_designs', 'deleted_at')) {
                        $join->whereNull('md.deleted_at');
                    }
                })
                ->when($hasConcreteGrades, function ($query) use ($hasConcreteGradeId) {
                    $query->leftJoin('mm_concrete_grades as cg', function ($join) use ($hasConcreteGradeId) {
                        if ($hasConcreteGradeId) {
                            $join->on('cg.id', '=', 'md.concrete_grade_id')
                                ->orWhere(function ($q) {
                                    $q->on('cg.plant_id', '=', 'md.plant_id')
                                        ->on('cg.name', '=', 'md.design_type');
                                });
                        } else {
                            $join->on('cg.plant_id', '=', 'md.plant_id')
                                ->on('cg.name', '=', 'md.design_type');
                        }
                        if (Schema::hasColumn('mm_concrete_grades', 'deleted_at')) {
                            $join->whereNull('cg.deleted_at');
                        }
                    });
                });

            $query->select(
                'md.id as mix_design_id',
                'md.design_name',
                'md.design_code',
                DB::raw("{$gradeExpr} as grade"),
                DB::raw('COALESCE(SUM(mm_batches.batch_size), 0) as total_batch_size'),
                DB::raw('COUNT(DISTINCT mm_batches.id) as total_batch_count')
            )
            ->groupBy($groupBys)
            ->orderByDesc('total_batch_size')
            ->limit(5);

            $this->applyFilters($query, $filters, 'start_time');

            $results = $query->get()->map(function ($row) {
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

        if ($results->isEmpty() && $hasDispatches) {
            $dispatchQuery = Dispatch::withoutGlobalScope('plant_id')
                ->when($hasSalesOrders, function ($q) {
                    $q->leftJoin('mm_sales_orders as wo', function ($join) {
                        $join->on('wo.id', '=', 'mm_dispatches.sales_order_id');
                        if (Schema::hasColumn('mm_sales_orders', 'deleted_at')) {
                            $join->whereNull('wo.deleted_at');
                        }
                    });
                })
                ->join('mm_mix_designs as md', function ($join) use ($hasSalesOrders) {
                    if ($hasSalesOrders) {
                        $join->on('md.id', '=', DB::raw('COALESCE(mm_dispatches.mixdesign_id, wo.mix_design_id)'));
                    } else {
                        $join->on('md.id', '=', 'mm_dispatches.mixdesign_id');
                    }
                    if (Schema::hasColumn('mm_mix_designs', 'deleted_at')) {
                        $join->whereNull('md.deleted_at');
                    }
                })
                ->when($hasConcreteGrades, function ($query) use ($hasConcreteGradeId) {
                    $query->leftJoin('mm_concrete_grades as cg', function ($join) use ($hasConcreteGradeId) {
                        if ($hasConcreteGradeId) {
                            $join->on('cg.id', '=', 'md.concrete_grade_id')
                                ->orWhere(function ($q) {
                                    $q->on('cg.plant_id', '=', 'md.plant_id')
                                        ->on('cg.name', '=', 'md.design_type');
                                });
                        } else {
                            $join->on('cg.plant_id', '=', 'md.plant_id')
                                ->on('cg.name', '=', 'md.design_type');
                        }
                        if (Schema::hasColumn('mm_concrete_grades', 'deleted_at')) {
                            $join->whereNull('cg.deleted_at');
                        }
                    });
                });

            if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
                $dispatchQuery->where(function ($q) {
                    $q->whereNull('mm_dispatches.dispatch_status')
                        ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
                });
            }

            $dispatchQuery->select(
                'md.id as mix_design_id',
                'md.design_name',
                'md.design_code',
                DB::raw("{$gradeExpr} as grade"),
                DB::raw('COALESCE(SUM(mm_dispatches.delivered_qty), 0) as total_batch_size'),
                DB::raw('COUNT(DISTINCT mm_dispatches.id) as total_batch_count')
            )
            ->groupBy($groupBys)
            ->orderByDesc('total_batch_size')
            ->limit(5);

            $this->applyFilters($dispatchQuery, $filters, 'dispatch_time');

            $results = $dispatchQuery->get()->map(function ($row) {
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

        return $results;
    }

    public function getStockDetails(array $filters)
    {
        $hasQuantity = Schema::hasTable('mm_quantity');
        $hasUnits = Schema::hasTable('mm_product_units');

        $query = Product::withoutGlobalScope('plant_id')
            ->when($hasQuantity, function ($q) {
                $q->leftJoin('mm_quantity as qty', function ($join) {
                    $join->on('qty.product_id', '=', 'mm_products.id')
                        ->on('qty.plant_id', '=', 'mm_products.plant_id');
                    if (Schema::hasColumn('mm_quantity', 'deleted_at')) {
                        $join->whereNull('qty.deleted_at');
                    }
                });
            })
            ->when($hasUnits, function ($q) {
                $q->leftJoin('mm_product_units as unit', 'unit.id', '=', 'mm_products.unit_id');
            });

        if (Schema::hasColumn('mm_products', 'deleted_at')) {
            $query->whereNull('mm_products.deleted_at');
        }

        if (Schema::hasColumn('mm_products', 'is_service')) {
            $query->where(function ($q) {
                $q->whereNull('mm_products.is_service')
                    ->orWhere('mm_products.is_service', false)
                    ->orWhere('mm_products.is_service', 0);
            });
        }

        $stockExpr = $hasQuantity
            ? 'COALESCE(SUM(qty.quantity), 0)'
            : '0';

        $unitNameExpr = $hasUnits ? 'unit.unit_name' : "''";
        $unitCodeExpr = $hasUnits ? 'unit.unit_code' : "''";

        $groupBys = [
            'mm_products.id',
            'mm_products.title',
            'mm_products.code',
            'mm_products.stock_alert',
        ];
        if ($hasUnits) {
            $groupBys[] = 'unit.unit_name';
            $groupBys[] = 'unit.unit_code';
        }

        $query->select(
            'mm_products.id as product_id',
            'mm_products.title as product_name',
            'mm_products.code as product_code',
            'mm_products.stock_alert',
            DB::raw("{$unitNameExpr} as unit_name"),
            DB::raw("{$unitCodeExpr} as unit_code"),
            DB::raw("{$stockExpr} as current_stock")
        )
        ->groupBy($groupBys)
        ->orderBy('mm_products.title');

        app(\App\Services\DashboardPlantAccess::class)->scope($query, 'mm_products.plant_id', $filters);

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
        $hasBatches = Schema::hasTable('mm_batches');
        $hasMachines = Schema::hasTable('mm_machines');

        $query = Dispatch::withoutGlobalScope('plant_id')
            ->when($hasBatches, function ($q) {
                $q->leftJoin('mm_batches as batches', 'batches.id', '=', 'mm_dispatches.batch_id');
            })
            ->when($hasMachines, function ($q) {
                $q->leftJoin('mm_machines as truck', function ($join) {
                    $join->on('truck.id', '=', 'mm_dispatches.truck_id');
                    if (Schema::hasColumn('mm_machines', 'deleted_at')) {
                        $join->whereNull('truck.deleted_at');
                    }
                });
            });

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $query->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $truckRegExpr = $hasMachines
            ? "COALESCE(truck.registration, 'N/A')"
            : "'N/A'";

        $m3Expr = $hasBatches
            ? 'COALESCE(SUM(COALESCE(mm_dispatches.delivered_qty, batches.batch_size, 0)), 0)'
            : 'COALESCE(SUM(COALESCE(mm_dispatches.delivered_qty, 0)), 0)';

        $groupBys = ['mm_dispatches.truck_id'];
        if ($hasMachines) {
            $groupBys[] = 'truck.registration';
        }

        $query->select(
            'mm_dispatches.truck_id',
            DB::raw("{$truckRegExpr} as vehicle_no"),
            DB::raw('COUNT(mm_dispatches.id) as count'),
            DB::raw("{$m3Expr} as m3")
        )
        ->groupBy($groupBys);

        $this->applyFilters($query, $filters, 'dispatch_time');

        return $query->get()->map(function ($d) {
            return [
                'vehicle_no' => $d->vehicle_no ?? 'N/A',
                'count' => (int) $d->count,
                'm3' => round((float) $d->m3, 2),
            ];
        });
    }

    public function getCustomerDetails(array $filters)
    {
        $hasConcreteGrades = Schema::hasTable('mm_concrete_grades');
        $hasBatches = Schema::hasTable('mm_batches');
        $hasBatchMaterials = Schema::hasTable('mm_batch_materials');
        $hasProductUnits = Schema::hasTable('mm_product_units');
        $hasSalesOrders = Schema::hasTable('mm_sales_orders');
        $hasConcreteGradeId = Schema::hasColumn('mm_mix_designs', 'concrete_grade_id');

        $gradeExpression = $hasConcreteGrades
            ? 'COALESCE(cg.name, md.design_type)'
            : 'md.design_type';

        $summaryQuery = Dispatch::withoutGlobalScope('plant_id')
            ->leftJoin('mm_patrons as customer', function ($join) {
                $join->on('customer.id', '=', 'mm_dispatches.customer_id');
                if (Schema::hasColumn('mm_patrons', 'deleted_at')) {
                    $join->whereNull('customer.deleted_at');
                }
            })
            ->when($hasSalesOrders, function ($q) {
                $q->leftJoin('mm_sales_orders as so', function ($join) {
                    $join->on('so.id', '=', 'mm_dispatches.sales_order_id');
                    if (Schema::hasColumn('mm_sales_orders', 'deleted_at')) {
                        $join->whereNull('so.deleted_at');
                    }
                })
                ->leftJoin('mm_patrons as so_customer', function ($join) {
                    $join->on('so_customer.id', '=', 'so.customer_id');
                    if (Schema::hasColumn('mm_patrons', 'deleted_at')) {
                        $join->whereNull('so_customer.deleted_at');
                    }
                });
            })
            ->leftJoin('mm_mix_designs as md', function ($join) use ($hasSalesOrders) {
                if ($hasSalesOrders) {
                    $join->on('md.id', '=', DB::raw('COALESCE(mm_dispatches.mixdesign_id, so.mix_design_id)'));
                } else {
                    $join->on('md.id', '=', 'mm_dispatches.mixdesign_id');
                }
                if (Schema::hasColumn('mm_mix_designs', 'deleted_at')) {
                    $join->whereNull('md.deleted_at');
                }
            })
            ->when($hasConcreteGrades, function ($query) use ($hasConcreteGradeId) {
                $query->leftJoin('mm_concrete_grades as cg', function ($join) use ($hasConcreteGradeId) {
                    if ($hasConcreteGradeId) {
                        $join->on('cg.id', '=', 'md.concrete_grade_id')
                            ->orWhere(function ($q) {
                                $q->on('cg.plant_id', '=', 'md.plant_id')
                                    ->on('cg.name', '=', 'md.design_type');
                            });
                    } else {
                        $join->on('cg.plant_id', '=', 'md.plant_id')
                            ->on('cg.name', '=', 'md.design_type');
                    }
                    if (Schema::hasColumn('mm_concrete_grades', 'deleted_at')) {
                        $join->whereNull('cg.deleted_at');
                    }
                });
            })
            ->where(function ($q) use ($hasSalesOrders) {
                $q->whereNotNull('mm_dispatches.customer_id');
                if ($hasSalesOrders) {
                    $q->orWhereNotNull('so.customer_id');
                }
            });

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $summaryQuery->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $resolvedCustomerIdExpr = $hasSalesOrders
            ? 'COALESCE(mm_dispatches.customer_id, so.customer_id)'
            : 'mm_dispatches.customer_id';

        $resolvedCustomerNameExpr = $hasSalesOrders
            ? "COALESCE(customer.legal_name, so_customer.legal_name, 'Walk-in Customer')"
            : "COALESCE(customer.legal_name, 'Walk-in Customer')";

        $groupBys = [
            DB::raw($resolvedCustomerIdExpr),
            DB::raw($resolvedCustomerNameExpr),
            'md.id',
            'md.design_name',
            'md.design_type',
        ];
        if ($hasConcreteGrades) {
            $groupBys[] = 'cg.name';
        }

        $summaryQuery->select(
            DB::raw("{$resolvedCustomerIdExpr} as customer_id"),
            DB::raw("{$resolvedCustomerNameExpr} as customer_name"),
            'md.id as mix_design_id',
            'md.design_name',
            DB::raw($gradeExpression . ' as grade'),
            DB::raw('COUNT(DISTINCT mm_dispatches.id) as total_dispatch_count')
        )
        ->groupBy($groupBys)
        ->orderBy('customer_name')
        ->orderBy('md.design_name');

        $this->applyFilters($summaryQuery, $filters, 'dispatch_time');

        $summaryRows = $summaryQuery->get();

        if ($summaryRows->isEmpty()) {
            return collect();
        }

        if (!$hasBatches || !$hasBatchMaterials) {
            return $summaryRows->map(function ($row) {
                return [
                    'customer_id' => (int) $row->customer_id,
                    'customer_name' => $row->customer_name,
                    'grade' => $row->grade,
                    'mix_design' => [
                        'id' => $row->mix_design_id ? (int) $row->mix_design_id : null,
                        'design_name' => $row->design_name,
                    ],
                    'total_dispatch_count' => [
                        'qty' => (int) $row->total_dispatch_count,
                        'unit' => 'count',
                    ],
                    'material_consumption' => [],
                ];
            })->values();
        }

        $materialsQuery = Dispatch::withoutGlobalScope('plant_id')
            ->leftJoin('mm_patrons as customer', function ($join) {
                $join->on('customer.id', '=', 'mm_dispatches.customer_id');
                if (Schema::hasColumn('mm_patrons', 'deleted_at')) {
                    $join->whereNull('customer.deleted_at');
                }
            })
            ->when($hasSalesOrders, function ($q) {
                $q->leftJoin('mm_sales_orders as so', function ($join) {
                    $join->on('so.id', '=', 'mm_dispatches.sales_order_id');
                    if (Schema::hasColumn('mm_sales_orders', 'deleted_at')) {
                        $join->whereNull('so.deleted_at');
                    }
                })
                ->leftJoin('mm_patrons as so_customer', function ($join) {
                    $join->on('so_customer.id', '=', 'so.customer_id');
                    if (Schema::hasColumn('mm_patrons', 'deleted_at')) {
                        $join->whereNull('so_customer.deleted_at');
                    }
                });
            })
            ->leftJoin('mm_mix_designs as md', function ($join) use ($hasSalesOrders) {
                if ($hasSalesOrders) {
                    $join->on('md.id', '=', DB::raw('COALESCE(mm_dispatches.mixdesign_id, so.mix_design_id)'));
                } else {
                    $join->on('md.id', '=', 'mm_dispatches.mixdesign_id');
                }
                if (Schema::hasColumn('mm_mix_designs', 'deleted_at')) {
                    $join->whereNull('md.deleted_at');
                }
            })
            ->leftJoin('mm_batches as batches', function ($join) {
                $join->on('batches.id', '=', 'mm_dispatches.batch_id');
                if (Schema::hasColumn('mm_batches', 'deleted_at')) {
                    $join->whereNull('batches.deleted_at');
                }
            })
            ->leftJoin('mm_batch_materials as bm', function ($join) {
                $join->on('bm.batch_id', '=', 'batches.id');
                if (Schema::hasColumn('mm_batch_materials', 'deleted_at')) {
                    $join->whereNull('bm.deleted_at');
                }
            })
            ->when($hasProductUnits, function ($q) {
                $q->leftJoin('mm_product_units as uom', function ($join) {
                    $join->on('uom.id', '=', 'bm.uom_id');
                    if (Schema::hasColumn('mm_product_units', 'deleted_at')) {
                        $join->whereNull('uom.deleted_at');
                    }
                });
            })
            ->when($hasConcreteGrades, function ($query) use ($hasConcreteGradeId) {
                $query->leftJoin('mm_concrete_grades as cg', function ($join) use ($hasConcreteGradeId) {
                    if ($hasConcreteGradeId) {
                        $join->on('cg.id', '=', 'md.concrete_grade_id')
                            ->orWhere(function ($q) {
                                $q->on('cg.plant_id', '=', 'md.plant_id')
                                    ->on('cg.name', '=', 'md.design_type');
                            });
                    } else {
                        $join->on('cg.plant_id', '=', 'md.plant_id')
                            ->on('cg.name', '=', 'md.design_type');
                    }
                    if (Schema::hasColumn('mm_concrete_grades', 'deleted_at')) {
                        $join->whereNull('cg.deleted_at');
                    }
                });
            })
            ->whereNotNull('bm.id');

        if (Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
            $materialsQuery->where(function ($q) {
                $q->whereNull('mm_dispatches.dispatch_status')
                    ->orWhere('mm_dispatches.dispatch_status', '!=', 'Cancelled');
            });
        }

        $matGroupBys = [
            DB::raw($resolvedCustomerIdExpr),
            'md.id',
            'md.design_type',
            'bm.material_name',
        ];
        if ($hasProductUnits) {
            $matGroupBys[] = 'uom.unit_name';
            $matGroupBys[] = 'uom.unit_code';
        }
        if ($hasConcreteGrades) {
            $matGroupBys[] = 'cg.name';
        }

        $materialsQuery->select(
            DB::raw("{$resolvedCustomerIdExpr} as customer_id"),
            'md.id as mix_design_id',
            DB::raw($gradeExpression . ' as grade'),
            'bm.material_name',
            DB::raw($hasProductUnits ? 'uom.unit_name' : "'' as unit_name"),
            DB::raw($hasProductUnits ? 'uom.unit_code' : "'' as unit_code"),
            DB::raw('COALESCE(SUM(bm.target_qty), 0) as total_target_qty'),
            DB::raw('COALESCE(SUM(bm.actual_qty), 0) as total_actual_qty'),
            DB::raw('COALESCE(SUM(bm.deviation_quantity), 0) as total_deviation_qty')
        )
        ->groupBy($matGroupBys)
        ->havingRaw('COALESCE(SUM(bm.target_qty), 0) > 0 OR COALESCE(SUM(bm.actual_qty), 0) > 0')
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
                ->filter(function ($materialRow) {
                    $target = round((float) $materialRow->total_target_qty, 3);
                    $actual = round((float) $materialRow->total_actual_qty, 3);

                    return $target > 0 || $actual > 0;
                })
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
                    'id' => $row->mix_design_id ? (int) $row->mix_design_id : null,
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
        $this->applyFilters($query, $filters);
        return $query->orderBy('created_at', 'desc')->limit(10)->get();
    }

    public function getPlants()
    {
        return app(\App\Services\DashboardPlantAccess::class)->query()->orderBy('name')->get(['id', 'name as plant_name']);
    }
}
