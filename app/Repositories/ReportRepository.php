<?php

namespace App\Repositories;

use App\Models\InvoiceItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ReportRepository
{
    /**
     * Build optimized query for Sales Register.
     *
     * Time Complexity: O(log n) for database retrieval using indexes, O(n) for streaming.
     *
     * @param array $filters
     * @return Builder
     */
    public function getSalesRegisterQuery(array $filters): Builder
    {
        $query = InvoiceItem::query()
            // Qualify all columns with table name to avoid JOIN ambiguity
            ->select([
                'mm_invoice_items.id',
                'mm_invoice_items.invoice_id',
                'mm_invoice_items.item_id',
                'mm_invoice_items.hsn_code',
                'mm_invoice_items.uom_id',
                'mm_invoice_items.item_name',
                'mm_invoice_items.quantity',
                'mm_invoice_items.price_unit',
                'mm_invoice_items.subtotal',
                'mm_invoice_items.line_tax_amount',
                'mm_invoice_items.line_total',
            ])
            ->whereNull('mm_invoice_items.deleted_at')
            ->whereHas('invoice', function ($q) {
                $q->where('invoice_type', 'sales')->whereNull('deleted_at');
            })
            ->with([
                'invoice' => function ($q) {
                    $q->withoutGlobalScopes()->whereNull('deleted_at')->select([
                        'id',
                        'prefix',
                        'invoice_number',
                        'invoice_date',
                        'partner_id',
                        'status',
                        'paid_amount',
                        'balance_amount',
                        'created_by',
                        'plant_id',
                        'invoice_label',
                        'ref_id',
                    ]);
                },
                'invoice.creator:id,username,email',
                'invoice.einvoiceRelation',
                'invoice.partner' => function ($q) {
                    $q->withoutGlobalScopes()->whereNull('deleted_at')->select(['id', 'legal_name', 'gstin', 'patron_type']);
                },
                'uom' => function ($q) {
                    $q->select(['id', 'unit_name', 'unit_code']);
                },
                'itemTaxes' => function ($q) {
                    $q->whereNull('deleted_at')->select(['id', 'order_items_id', 'name', 'rate', 'amount']);
                },
            ]);

        $documentStatus = $filters['document_status'] ?? 'active';
        if ($documentStatus !== 'all') {
            $query->whereHas('invoice', function ($q) use ($documentStatus) {
                $expression = "LOWER(COALESCE(status, ''))";
                $q->whereRaw($expression.($documentStatus === 'cancelled' ? ' IN' : ' NOT IN')." ('cancel', 'cancelled', 'canceled')");
            });
        }

        // Filter: Date Range (Mandatory)
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $query->whereHas('invoice', function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('invoice_date', [$fromDate->toDateString(), $toDate->toDateTimeString()]);
        });

        // Filter: Plant Scoping (session active_plant_id OR explicit filter)
        $plantId = $filters['plant_id'] ?? $filters['branch_id'] ?? Session::get('active_plant_id');
        if ($plantId) {
            $query->whereHas('invoice', function ($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            });
        }

        // Filter: Customer (partner_id)
        if (!empty($filters['customer_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('mm_invoices.partner_id', $filters['customer_id'])
                    ->orWhere(function ($fallback) use ($filters) {
                        $fallback->whereRaw('COALESCE(mm_invoices.partner_id, 0) = 0')
                            ->where('register_customer.id', $filters['customer_id']);
                    });
            });
        }

        // Filter: GST Type (Intra vs Inter)
        if (!empty($filters['gst_type'])) {
            if ($filters['gst_type'] === 'intra') {
                $query->whereHas('itemTaxes', function ($q) {
                    $q->where('name', 'LIKE', '%CGST%');
                });
            } elseif ($filters['gst_type'] === 'inter') {
                $query->whereHas('itemTaxes', function ($q) {
                    $q->where('name', 'LIKE', '%IGST%');
                });
            }
        }

        // Filter: Invoice Type/Label
        if (!empty($filters['invoice_type'])) {
            $query->whereHas('invoice', function ($q) use ($filters) {
                $q->where('invoice_label', $filters['invoice_type']);
            });
        }

        // Filter: Product (mix_design_id)
        if (!empty($filters['product_id'])) {
            $query->where('mm_invoice_items.item_id', $filters['product_id']);
        }

        // Filter: Salesman (created_by mapping)
        if (!empty($filters['salesman_id'])) {
            $query->whereHas('invoice', function ($q) use ($filters) {
                $q->where('created_by', $filters['salesman_id']);
            });
        }

        // Filter: Payment Status
        if (!empty($filters['payment_status'])) {
            $status = $filters['payment_status'];
            $query->whereHas('invoice', function ($q) use ($status) {
                if ($status === 'paid') {
                    $q->where(function ($sub) {
                        $sub->whereRaw('LOWER(status) = ?', ['paid'])->orWhere('balance_amount', '<=', 0);
                    });
                } elseif ($status === 'unpaid') {
                    $q->where('paid_amount', 0)->where('balance_amount', '>', 0)->whereRaw('LOWER(status) != ?', ['paid']);
                } elseif ($status === 'partial') {
                    $q->where('paid_amount', '>', 0)->where('balance_amount', '>', 0);
                }
            });
        }

        // JOIN for ordering — must come after all whereHas filters
        $query->join('mm_invoices', 'mm_invoice_items.invoice_id', '=', 'mm_invoices.id')
            // Only Dispatch references point to dispatch IDs. Keep every lookup in the invoice's plant.
            ->leftJoin('mm_dispatches as register_dispatch', function ($join) {
                $join->on('register_dispatch.id', '=', 'mm_invoices.ref_id')
                    ->on('register_dispatch.plant_id', '=', 'mm_invoices.plant_id')
                    ->where('mm_invoices.invoice_label', 'Dispatch')->whereNull('register_dispatch.deleted_at');
            })
            ->leftJoin('mm_sales_orders as register_order', function ($join) {
                $join->on('register_order.id', '=', 'register_dispatch.sales_order_id')
                    ->on('register_order.plant_id', '=', 'mm_invoices.plant_id')->whereNull('register_order.deleted_at');
            })
            ->leftJoin('mm_patrons as register_customer', function ($join) {
                $join->on('register_customer.id', '=', DB::raw('COALESCE(NULLIF(register_dispatch.customer_id, 0), register_order.customer_id)'))
                    ->on('register_customer.plant_id', '=', 'mm_invoices.plant_id')->whereNull('register_customer.deleted_at');
            })
            ->leftJoin('mm_sites as register_site', function ($join) {
                $join->on('register_site.id', '=', 'register_dispatch.unload_site_id')
                    ->on('register_site.plant_id', '=', 'mm_invoices.plant_id')->whereNull('register_site.deleted_at');
            })
            ->leftJoin('mm_machines as register_truck', function ($join) {
                $join->on('register_truck.id', '=', 'register_dispatch.truck_id')
                    ->on('register_truck.plant_id', '=', 'mm_invoices.plant_id')->whereNull('register_truck.deleted_at');
            })
            ->addSelect([
                'register_dispatch.payment_mode as register_payment_mode',
                'register_site.name as register_unloading',
                'register_truck.registration as register_truck_number',
                'register_customer.legal_name as register_customer_name',
                'register_customer.gstin as register_customer_gstin',
                'register_customer.patron_type as register_party_type',
            ])
            ->whereNull('mm_invoices.deleted_at')
            ->orderBy('mm_invoices.invoice_date', 'asc')
            ->orderBy('mm_invoices.id', 'asc')
            ->orderBy('mm_invoice_items.id', 'asc');

        return $query;
    }

    /**
     * Build optimized query for Purchase Register.
     *
     * Time Complexity: O(log n) for database retrieval, O(n) for streaming.
     *
     * @param array $filters
     * @return Builder
     */
    public function getPurchaseRegisterQuery(array $filters): Builder
    {
        $query = PurchaseOrderItem::query()
            ->whereNull('mm_purchase_order_items.deleted_at')
            ->whereHas('order', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->select([
                'mm_purchase_order_items.id',
                'mm_purchase_order_items.order_id',
                'mm_purchase_order_items.product_id',
                'mm_purchase_order_items.tax_id',
                'mm_purchase_order_items.hsn_code',
                'mm_purchase_order_items.description',
                'mm_purchase_order_items.product_uom',
                'mm_purchase_order_items.product_quantity',
                'mm_purchase_order_items.unit_price',
                'mm_purchase_order_items.price_subtotal',
                'mm_purchase_order_items.price_tax',
                'mm_purchase_order_items.price_total',
            ])
            ->with([
                'order' => function ($q) {
                    $q->withoutGlobalScopes()->whereNull('deleted_at')->select([
                        'id',
                        'po_number',
                        'bill_number',
                        'date_order',
                        'billed_date',
                        'created_at',
                        'created_by',
                        'state',
                        'vendor_id',
                        'plant_id',
                    ]);
                },
                'order.creator:id,username,email',
                'order.vendor' => function ($q) {
                    $q->withoutGlobalScopes()->whereNull('deleted_at')->select(['id', 'legal_name', 'gstin']);
                },
                'order.plant' => function ($q) {
                    $q->select(['id', 'gstin']);
                },
                'product' => function ($q) {
                    $q->select(['id', 'title']);
                },
                'uom' => function ($q) {
                    $q->select(['id', 'unit_name', 'unit_code']);
                },
                'tax' => function ($q) {
                    $q->select(['id', 'tax_name', 'tax_rate', 'tax_group']);
                },
            ]);

        $documentStatus = $filters['document_status'] ?? 'active';
        if ($documentStatus !== 'all') {
            $query->whereHas('order', function ($q) use ($documentStatus) {
                $expression = "LOWER(COALESCE(state, ''))";
                $q->whereRaw($expression.($documentStatus === 'cancelled' ? ' IN' : ' NOT IN')." ('cancel', 'cancelled', 'canceled')");
            });
        }

        // Filter: Date Range (Mandatory)
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $query->whereHas('order', function ($q) use ($fromDate, $toDate) {
            $q->whereNull('deleted_at')->whereBetween(DB::raw('COALESCE(billed_date, date_order, created_at)'), [$fromDate->toDateString(), $toDate->toDateTimeString()]);
        });

        // Filter: Plant Scoping (session active_plant_id OR explicit filter)
        $plantId = $filters['plant_id'] ?? $filters['branch_id'] ?? Session::get('active_plant_id');
        if ($plantId) {
            $query->whereHas('order', function ($q) use ($plantId) {
                $q->whereNull('deleted_at')->where('plant_id', $plantId);
            });
        }

        // Filter: Supplier (vendor_id)
        if (!empty($filters['supplier_id'])) {
            $query->whereHas('order', function ($q) use ($filters) {
                $q->whereNull('deleted_at')->where('vendor_id', $filters['supplier_id']);
            });
        }

        // Filter: Product
        if (!empty($filters['product_id'])) {
            $query->where('mm_purchase_order_items.product_id', $filters['product_id']);
        }

        $query->join('mm_purchase_orders', 'mm_purchase_order_items.order_id', '=', 'mm_purchase_orders.id')
            ->whereNull('mm_purchase_orders.deleted_at');

        // Apply the same explicit-group / state rules used to display the tax split.
        if (!empty($filters['gst_type'])) {
            $query->leftJoin('mm_taxes as register_tax', function ($join) {
                $join->on('mm_purchase_order_items.tax_id', '=', 'register_tax.id')->whereNull('register_tax.deleted_at');
            })->leftJoin('mm_patrons as register_vendor', function ($join) {
                $join->on('mm_purchase_orders.vendor_id', '=', 'register_vendor.id')->whereNull('register_vendor.deleted_at');
            })->leftJoin('mm_plants as register_plant', 'mm_purchase_orders.plant_id', '=', 'register_plant.id');
            $group = "UPPER(TRIM(COALESCE(register_tax.tax_group, '')))";
            $inter = "($group LIKE '%IGST%' OR ($group IN ('', 'GST') AND LENGTH(TRIM(COALESCE(register_plant.gstin, ''))) >= 2 AND LENGTH(TRIM(COALESCE(register_vendor.gstin, ''))) >= 2 AND SUBSTR(TRIM(register_plant.gstin), 1, 2) != SUBSTR(TRIM(register_vendor.gstin), 1, 2)))";
            $intra = "($group LIKE '%CGST%' OR $group LIKE '%SGST%' OR $group LIKE '%UTGST%' OR $group LIKE '%UGST%' OR ($group IN ('', 'GST') AND NOT $inter))";
            $query->where('mm_purchase_order_items.price_tax', '!=', 0)
                ->whereRaw($filters['gst_type'] === 'inter' ? $inter : $intra);
        }

        return $query->orderByRaw('COALESCE(mm_purchase_orders.billed_date, mm_purchase_orders.date_order, mm_purchase_orders.created_at) ASC')
            ->orderBy('mm_purchase_orders.id', 'asc')
            ->orderBy('mm_purchase_order_items.id', 'asc');
    }

    /**
     * Compute aggregate totals for Sales Register query (subquery approach, no mergeBindings).
     */
    public function getSalesTotals(array $filters): array
    {
        // Build a clean subquery of item IDs matching the filters
        $subQuery = $this->getSalesRegisterQuery($filters)
            ->getQuery()
            ->cloneWithout(['orders', 'columns'])
            ->select('mm_invoice_items.id');

        $result = DB::table('mm_invoice_items')
            ->whereIn('mm_invoice_items.id', $subQuery)
            ->whereNull('mm_invoice_items.deleted_at')
            ->selectRaw('
                COALESCE(SUM(quantity), 0)        AS total_qty,
                COALESCE(SUM(subtotal), 0)         AS total_taxable,
                COALESCE(SUM(line_tax_amount), 0)  AS total_gst,
                COALESCE(SUM(line_total), 0)       AS grand_total
            ')
            ->first();

        $taxTotals = DB::table('mm_order_taxes')
            ->where('order_type', 'Invoice')
            ->whereIn('order_items_id', $subQuery)
            ->whereNull('deleted_at')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN name LIKE '%CGST%' THEN amount ELSE 0 END), 0) as total_cgst,
                COALESCE(SUM(CASE WHEN name LIKE '%SGST%' OR name LIKE '%UTGST%' OR name LIKE '%UGST%' THEN amount ELSE 0 END), 0) as total_sgst,
                COALESCE(SUM(CASE WHEN name LIKE '%IGST%' THEN amount ELSE 0 END), 0) as total_igst
            ")
            ->first();

        return array_merge((array) $result, (array) $taxTotals);
    }

    /**
     * Compute aggregate totals for Purchase Register query (subquery approach).
     */
    public function getPurchaseTotals(array $filters): array
    {
        $plantId = $filters['plant_id'] ?? $filters['branch_id'] ?? Session::get('active_plant_id');
        $plantGstin = DB::table('mm_plants')->where('id', $plantId)->value('gstin');
        $plantState = $plantGstin && strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';

        $subQuery = $this->getPurchaseRegisterQuery($filters)
            ->getQuery()
            ->cloneWithout(['orders', 'columns'])
            ->select('mm_purchase_order_items.id');

        $result = DB::table('mm_purchase_order_items as poi')
            ->join('mm_purchase_orders as po', 'poi.order_id', '=', 'po.id')
            ->join('mm_patrons as pat', 'po.vendor_id', '=', 'pat.id')
            ->whereIn('poi.id', $subQuery)
            ->whereNull('poi.deleted_at')
            ->whereNull('po.deleted_at')
            ->whereNull('pat.deleted_at')
            ->selectRaw("
                COALESCE(SUM(poi.product_quantity), 0) AS total_qty,
                COALESCE(SUM(poi.price_subtotal), 0)   AS total_taxable,
                COALESCE(SUM(poi.price_tax), 0)        AS total_gst,
                COALESCE(SUM(poi.price_total), 0)      AS grand_total,
                COALESCE(SUM(CASE WHEN LEFT(COALESCE(pat.gstin, ''), 2) = ? OR COALESCE(pat.gstin, '') = '' THEN poi.price_tax / 2 ELSE 0 END), 0) AS total_cgst,
                COALESCE(SUM(CASE WHEN LEFT(COALESCE(pat.gstin, ''), 2) = ? OR COALESCE(pat.gstin, '') = '' THEN poi.price_tax / 2 ELSE 0 END), 0) AS total_sgst,
                COALESCE(SUM(CASE WHEN LEFT(COALESCE(pat.gstin, ''), 2) != ? AND COALESCE(pat.gstin, '') != '' THEN poi.price_tax ELSE 0 END), 0) AS total_igst
            ", [$plantState, $plantState, $plantState])
            ->first();

        return (array) $result;
    }

    /**
     * Build optimized query for Machine Summary.
     */
    public function getMachineSummaryQuery(array $filters): Builder
    {
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
        
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $tripSub = $this->dispatchMachineMetrics($fromDate, $toDate, $plantId)
            ->addSelect('d.truck_id')->groupBy('d.truck_id');

        // Subquery for general expenses from mm_expenses
        $expSub = DB::table('mm_expenses as e')
            ->selectRaw('
                e.machine_id,
                SUM(COALESCE(e.amount, 0)) as general_expenses
            ')
            ->whereNull('e.deleted_at')
            ->whereBetween('e.date', [$fromDate, $toDate])
            ->groupBy('e.machine_id');

        // Main machine query
        $query = \App\Models\Machine::query()
            ->whereNull('mm_machines.deleted_at')
            ->leftJoinSub($tripSub, 'trips', 'mm_machines.id', '=', 'trips.truck_id')
            ->leftJoinSub($expSub, 'expenses', 'mm_machines.id', '=', 'expenses.machine_id')
            ->select([
                'mm_machines.id',
                'mm_machines.registration',
                'mm_machines.vehicle_model',
                'mm_machines.vehicle_type',
                'mm_machines.make_year',
                'mm_machines.capacity',
                'mm_machines.owner_id',
                DB::raw('COALESCE(trips.total_trips, 0) as trips_count'),
                DB::raw('COALESCE(trips.total_qty, 0) as total_qty'),
                DB::raw('COALESCE(trips.total_weight_tons, 0) as total_weight_tons'),
                DB::raw('COALESCE(trips.total_revenue, 0) as total_revenue'),
                DB::raw('COALESCE(trips.total_trip_cost, 0) as total_trip_cost'),
                DB::raw('COALESCE(expenses.general_expenses, 0) as general_expenses'),
            ])
            ->with(['owner', 'documents'])
            ->orderBy('mm_machines.registration', 'asc');

        if ($plantId) {
            $query->where('mm_machines.plant_id', $plantId);
        }

        if (!empty($filters['machine_id'])) {
            $query->where('mm_machines.id', $filters['machine_id']);
        }

        return $query;
    }

    /**
     * Compute aggregate totals for Machine Summary report.
     */
    public function getMachineSummaryTotals(array $filters): array
    {
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $tripTotals = $this->dispatchMachineMetrics($fromDate, $toDate, $plantId)
            ->join('mm_machines as m', 'd.truck_id', '=', 'm.id')
            ->whereNull('m.deleted_at')
            ->when($plantId, fn ($query) => $query->where('m.plant_id', $plantId))
            ->when(!empty($filters['machine_id']), fn ($query) => $query->where('m.id', $filters['machine_id']))
            ->first();

        // 2. Sum general expenses directly
        $expMetrics = DB::table('mm_expenses as e')
            ->join('mm_machines as m', 'e.machine_id', '=', 'm.id')
            ->whereNull('e.deleted_at')
            ->whereNull('m.deleted_at')
            ->whereBetween('e.date', [$fromDate, $toDate]);

        if ($plantId) {
            $expMetrics->where('m.plant_id', $plantId);
        }

        if (!empty($filters['machine_id'])) {
            $expMetrics->where('m.id', $filters['machine_id']);
        }

        $totalGeneralExpenses = $expMetrics->sum(DB::raw('COALESCE(e.amount, 0)'));

        return [
            'total_trips' => (int) ($tripTotals->total_trips ?? 0),
            'total_qty' => (float) ($tripTotals->total_qty ?? 0),
            'total_weight_tons' => (float) ($tripTotals->total_weight_tons ?? 0),
            'total_revenue' => (float) ($tripTotals->total_revenue ?? 0),
            'total_trip_cost' => (float) ($tripTotals->total_trip_cost ?? 0),
            'total_general_expenses' => (float) $totalGeneralExpenses,
        ];
    }

    /**
     * Build optimized query for Vehicle Wise Profit & Loss.
     */
    public function getVehiclePLQuery(array $filters): Builder
    {
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
        
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $tripSub = $this->dispatchMachineMetrics($fromDate, $toDate, $plantId)
            ->addSelect('d.truck_id')->groupBy('d.truck_id');

        // Subquery for categorized expenses from mm_expenses
        $expSub = DB::table('mm_expenses as e')
            ->join('mm_expense_types as et', 'e.expense_type_id', '=', 'et.id')
            ->selectRaw("
                e.machine_id,
                SUM(CASE WHEN LOWER(et.name) LIKE '%fuel%' THEN e.amount ELSE 0 END) as fuel_expenses,
                SUM(CASE WHEN LOWER(et.name) LIKE '%maintenance%' THEN e.amount ELSE 0 END) as maintenance_expenses,
                SUM(CASE WHEN LOWER(et.name) NOT LIKE '%fuel%' AND LOWER(et.name) NOT LIKE '%maintenance%' THEN e.amount ELSE 0 END) as other_expenses
            ")
            ->whereNull('e.deleted_at')
            ->whereBetween('e.date', [$fromDate, $toDate])
            ->groupBy('e.machine_id');

        // Main machine query
        $query = \App\Models\Machine::query()
            ->whereNull('mm_machines.deleted_at')
            ->leftJoinSub($tripSub, 'trips', 'mm_machines.id', '=', 'trips.truck_id')
            ->leftJoinSub($expSub, 'expenses', 'mm_machines.id', '=', 'expenses.machine_id')
            ->select([
                'mm_machines.id',
                'mm_machines.registration',
                'mm_machines.vehicle_model',
                'mm_machines.vehicle_type',
                DB::raw('COALESCE(trips.total_revenue, 0) as trip_revenue'),
                DB::raw('COALESCE(trips.total_trip_cost, 0) as trip_cost'),
                DB::raw('COALESCE(expenses.fuel_expenses, 0) as fuel_expenses'),
                DB::raw('COALESCE(expenses.maintenance_expenses, 0) as maintenance_expenses'),
                DB::raw('COALESCE(expenses.other_expenses, 0) as other_expenses'),
            ])
            ->orderBy('mm_machines.registration', 'asc');

        if ($plantId) {
            $query->where('mm_machines.plant_id', $plantId);
        }

        if (!empty($filters['machine_id'])) {
            $query->where('mm_machines.id', $filters['machine_id']);
        }

        return $query;
    }

    /**
     * Compute aggregate totals for Vehicle Wise Profit & Loss report.
     */
    public function getVehiclePLTotals(array $filters): array
    {
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $tripTotals = $this->dispatchMachineMetrics($fromDate, $toDate, $plantId)
            ->join('mm_machines as m', 'd.truck_id', '=', 'm.id')
            ->whereNull('m.deleted_at')
            ->when($plantId, fn ($query) => $query->where('m.plant_id', $plantId))
            ->when(!empty($filters['machine_id']), fn ($query) => $query->where('m.id', $filters['machine_id']))
            ->first();

        // 2. Sum categorized expenses directly
        $expMetrics = DB::table('mm_expenses as e')
            ->join('mm_expense_types as et', 'e.expense_type_id', '=', 'et.id')
            ->join('mm_machines as m', 'e.machine_id', '=', 'm.id')
            ->whereNull('e.deleted_at')
            ->whereNull('m.deleted_at')
            ->whereBetween('e.date', [$fromDate, $toDate]);

        if ($plantId) {
            $expMetrics->where('m.plant_id', $plantId);
        }

        if (!empty($filters['machine_id'])) {
            $expMetrics->where('m.id', $filters['machine_id']);
        }

        $expTotals = $expMetrics->selectRaw("
            SUM(CASE WHEN LOWER(et.name) LIKE '%fuel%' THEN e.amount ELSE 0 END) as total_fuel_expenses,
            SUM(CASE WHEN LOWER(et.name) LIKE '%maintenance%' THEN e.amount ELSE 0 END) as total_maintenance_expenses,
            SUM(CASE WHEN LOWER(et.name) NOT LIKE '%fuel%' AND LOWER(et.name) NOT LIKE '%maintenance%' THEN e.amount ELSE 0 END) as total_other_expenses
        ")->first();

        return [
            'trip_revenue' => (float) ($tripTotals->total_revenue ?? 0),
            'trip_cost' => (float) ($tripTotals->total_trip_cost ?? 0),
            'fuel_expenses' => (float) ($expTotals->total_fuel_expenses ?? 0),
            'maintenance_expenses' => (float) ($expTotals->total_maintenance_expenses ?? 0),
            'other_expenses' => (float) ($expTotals->total_other_expenses ?? 0),
        ];
    }
    /** Shared dispatch aggregates for report rows, totals and exports. */
    private function dispatchMachineMetrics(Carbon $fromDate, Carbon $toDate, $plantId): \Illuminate\Database\Query\Builder
    {
        return DB::table('mm_dispatches as d')
            ->leftJoin('mm_batches as b', function ($join) {
                $join->on('b.id', '=', 'd.batch_id')->on('b.plant_id', '=', 'd.plant_id');
            })
            ->whereNull('d.deleted_at')
            ->whereNull('b.deleted_at')
            ->where(fn ($query) => $query->whereNull('b.status')->orWhere('b.status', '!=', \App\Models\Batch::STATUS_CANCELLED))
            ->where(fn ($query) => $query->whereNull('d.dispatch_status')->orWhere('d.dispatch_status', '!=', 'Cancelled'))
            ->when($plantId, fn ($query) => $query->where('d.plant_id', $plantId))
            ->whereBetween(DB::raw('COALESCE(d.dispatch_time, d.created_at)'), [$fromDate, $toDate])
            // Weights are stored in MT; saved totals already include tax and adjustments.
            // Production cost is not recorded here; use the available transport cost.
            ->selectRaw('
                COUNT(d.id) as total_trips,
                SUM(CASE WHEN d.delivered_qty > 0 THEN d.delivered_qty ELSE COALESCE(b.batch_size, 0) END) as total_qty,
                SUM(COALESCE(d.net_weight, d.loaded_weight_truck - d.empty_weight_truck, 0)) as total_weight_tons,
                SUM(COALESCE(d.load_total_amount, 0)) as total_revenue,
                SUM(COALESCE(d.transport_expenses, 0)) as total_trip_cost
            ');
    }

}
