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
                'mm_invoice_items.item_id as mix_design_id',
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
                'invoice.partner' => function ($q) {
                    $q->withoutGlobalScopes()->whereNull('deleted_at')->select(['id', 'legal_name', 'gstin']);
                },
                'uom' => function ($q) {
                    $q->select(['id', 'unit_name', 'unit_code']);
                },
                'itemTaxes' => function ($q) {
                    $q->whereNull('deleted_at')->select(['id', 'order_items_id', 'name', 'rate', 'amount']);
                },
            ]);

        // Filter: Date Range (Mandatory)
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $query->whereHas('invoice', function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('invoice_date', [$fromDate, $toDate]);
        });

        // Filter: Plant Scoping (session active_plant_id OR explicit filter)
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
        if ($plantId) {
            $query->whereHas('invoice', function ($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            });
        }

        // Filter: Customer (partner_id)
        if (!empty($filters['customer_id'])) {
            $query->whereHas('invoice', function ($q) use ($filters) {
                $q->where('partner_id', $filters['customer_id']);
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
                        $sub->where('status', 'paid')->orWhere('balance_amount', '<=', 0);
                    });
                } elseif ($status === 'unpaid') {
                    $q->where('paid_amount', 0)->where('status', '!=', 'paid');
                } elseif ($status === 'partial') {
                    $q->where('paid_amount', '>', 0)->where('balance_amount', '>', 0);
                }
            });
        }

        // JOIN for ordering — must come after all whereHas filters
        $query->join('mm_invoices', 'mm_invoice_items.invoice_id', '=', 'mm_invoices.id')
            ->whereNull('mm_invoices.deleted_at')
            ->orderBy('mm_invoices.invoice_date', 'asc')
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
                        'vendor_id',
                        'plant_id',
                    ]);
                },
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

        // Filter: Date Range (Mandatory)
        $fromDate = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : now()->startOfMonth();
        $toDate   = isset($filters['to_date'])   ? Carbon::parse($filters['to_date'])->endOfDay()     : now()->endOfDay();

        $query->whereHas('order', function ($q) use ($fromDate, $toDate) {
            $q->whereNull('deleted_at')->where(function ($sq) use ($fromDate, $toDate) {
                $sq->whereBetween('date_order', [$fromDate, $toDate])
                   ->orWhereBetween('billed_date', [$fromDate, $toDate])
                   ->orWhereBetween('created_at', [$fromDate, $toDate]);
            });
        });

        // Filter: Plant Scoping (session active_plant_id OR explicit filter)
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
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

        // Filter: GST Type — compare first 2 digits of plant GSTIN vs vendor GSTIN (Indian state code)
        // Intra-state (CGST+SGST): same state code | Inter-state (IGST): different state code
        if (!empty($filters['gst_type'])) {
            $gstType     = $filters['gst_type'];
            $lookupPlant = $plantId ?? Session::get('active_plant_id');

            $plantGstin = DB::table('mm_plants')
                ->where('id', $lookupPlant)
                ->value('gstin');

            if ($plantGstin && strlen($plantGstin) >= 2) {
                $plantStateCode = substr($plantGstin, 0, 2);

                // First join orders table so vendor_id is available
                $query->join('mm_purchase_orders as po_gst', 'mm_purchase_order_items.order_id', '=', 'po_gst.id')
                    ->whereNull('po_gst.deleted_at')
                    ->join('mm_patrons as gst_vendor', 'po_gst.vendor_id', '=', 'gst_vendor.id')
                    ->whereNull('gst_vendor.deleted_at')
                    ->where(function ($q) use ($gstType, $plantStateCode) {
                        if ($gstType === 'intra') {
                            $q->whereRaw("LEFT(gst_vendor.gstin, 2) = ?", [$plantStateCode]);
                        } else {
                            $q->whereRaw("LEFT(gst_vendor.gstin, 2) != ?", [$plantStateCode])
                              ->whereNotNull('gst_vendor.gstin')
                              ->where('gst_vendor.gstin', '!=', '');
                        }
                    });

                // Ordering already done via po_gst alias — skip the duplicate join below
                $query->orderBy('po_gst.date_order', 'asc')
                    ->orderBy('mm_purchase_order_items.id', 'asc');

                return $query;
            }
        }

        // JOIN for ordering (only when no GST type filter — avoids duplicate join)
        $query->join('mm_purchase_orders', 'mm_purchase_order_items.order_id', '=', 'mm_purchase_orders.id')
            ->whereNull('mm_purchase_orders.deleted_at')
            ->orderBy('mm_purchase_orders.date_order', 'asc')
            ->orderBy('mm_purchase_order_items.id', 'asc');

        return $query;
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
        $plantId = $filters['branch_id'] ?? $filters['plant_id'] ?? Session::get('active_plant_id');
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

        // Subquery for trip metrics
        $tripSub = DB::table('mm_trips as t')
            ->join('mm_trip_financials as tf', 't.id', '=', 'tf.trip_id')
            ->leftJoin('mm_trip_weights as tw', 't.id', '=', 'tw.trip_id')
            ->selectRaw('
                t.truck_id,
                COUNT(t.id) as total_trips,
                SUM(COALESCE(tf.product_units, 0)) as total_qty,
                SUM(COALESCE(tw.loaded_weight_load - tw.empty_weight_load, 0)) / 1000.0 as total_weight_tons,
                SUM(COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount ELSE tf.product_amount END, 0) + 
                    COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount * COALESCE(tf.updated_tax_rate, 0) / 100.0 ELSE tf.product_tax_amount END, 0) + 
                    COALESCE(tf.transport_unit * tf.transport_rate, 0) + 
                    COALESCE(tf.transport_unit * tf.transport_rate * COALESCE(tf.transport_tax_rate, 0) / 100.0, 0) + 
                    COALESCE(tf.pass_amount, 0) - 
                    COALESCE(tf.discount_amount, 0)
                ) as total_revenue,
                SUM(COALESCE(tf.cost_of_product, 0) + COALESCE(tf.transport_expenses, 0)) as total_trip_cost
            ')
            ->whereNull('t.deleted_at')
            ->whereBetween('t.created_at', [$fromDate, $toDate])
            ->groupBy('t.truck_id');

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

        // 1. Sum trip metrics directly
        $tripMetrics = DB::table('mm_trips as t')
            ->join('mm_trip_financials as tf', 't.id', '=', 'tf.trip_id')
            ->leftJoin('mm_trip_weights as tw', 't.id', '=', 'tw.trip_id')
            ->join('mm_machines as m', 't.truck_id', '=', 'm.id')
            ->whereNull('t.deleted_at')
            ->whereNull('m.deleted_at')
            ->whereBetween('t.created_at', [$fromDate, $toDate]);

        if ($plantId) {
            $tripMetrics->where('m.plant_id', $plantId);
        }

        $tripTotals = $tripMetrics->selectRaw('
            COUNT(t.id) as total_trips,
            SUM(COALESCE(tf.product_units, 0)) as total_qty,
            SUM(COALESCE(tw.loaded_weight_load - tw.empty_weight_load, 0)) / 1000.0 as total_weight_tons,
            SUM(COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount ELSE tf.product_amount END, 0) + 
                COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount * COALESCE(tf.updated_tax_rate, 0) / 100.0 ELSE tf.product_tax_amount END, 0) + 
                COALESCE(tf.transport_unit * tf.transport_rate, 0) + 
                COALESCE(tf.transport_unit * tf.transport_rate * COALESCE(tf.transport_tax_rate, 0) / 100.0, 0) + 
                COALESCE(tf.pass_amount, 0) - 
                COALESCE(tf.discount_amount, 0)
            ) as total_revenue,
            SUM(COALESCE(tf.cost_of_product, 0) + COALESCE(tf.transport_expenses, 0)) as total_trip_cost
        ')->first();

        // 2. Sum general expenses directly
        $expMetrics = DB::table('mm_expenses as e')
            ->join('mm_machines as m', 'e.machine_id', '=', 'm.id')
            ->whereNull('e.deleted_at')
            ->whereNull('m.deleted_at')
            ->whereBetween('e.date', [$fromDate, $toDate]);

        if ($plantId) {
            $expMetrics->where('m.plant_id', $plantId);
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

        // Subquery for trip metrics
        $tripSub = DB::table('mm_trips as t')
            ->join('mm_trip_financials as tf', 't.id', '=', 'tf.trip_id')
            ->selectRaw('
                t.truck_id,
                SUM(COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount ELSE tf.product_amount END, 0) + 
                    COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount * COALESCE(tf.updated_tax_rate, 0) / 100.0 ELSE tf.product_tax_amount END, 0) + 
                    COALESCE(tf.transport_unit * tf.transport_rate, 0) + 
                    COALESCE(tf.transport_unit * tf.transport_rate * COALESCE(tf.transport_tax_rate, 0) / 100.0, 0) + 
                    COALESCE(tf.pass_amount, 0) - 
                    COALESCE(tf.discount_amount, 0)
                ) as total_revenue,
                SUM(COALESCE(tf.cost_of_product, 0) + COALESCE(tf.transport_expenses, 0)) as total_trip_cost
            ')
            ->whereNull('t.deleted_at')
            ->whereBetween('t.created_at', [$fromDate, $toDate])
            ->groupBy('t.truck_id');

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

        // 1. Sum trip metrics directly
        $tripMetrics = DB::table('mm_trips as t')
            ->join('mm_trip_financials as tf', 't.id', '=', 'tf.trip_id')
            ->join('mm_machines as m', 't.truck_id', '=', 'm.id')
            ->whereNull('t.deleted_at')
            ->whereNull('m.deleted_at')
            ->whereBetween('t.created_at', [$fromDate, $toDate]);

        if ($plantId) {
            $tripMetrics->where('m.plant_id', $plantId);
        }

        $tripTotals = $tripMetrics->selectRaw('
            SUM(COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount ELSE tf.product_amount END, 0) + 
                COALESCE(CASE WHEN tf.updated_product_amount > 0 THEN tf.updated_product_amount * COALESCE(tf.updated_tax_rate, 0) / 100.0 ELSE tf.product_tax_amount END, 0) + 
                COALESCE(tf.transport_unit * tf.transport_rate, 0) + 
                COALESCE(tf.transport_unit * tf.transport_rate * COALESCE(tf.transport_tax_rate, 0) / 100.0, 0) + 
                COALESCE(tf.pass_amount, 0) - 
                COALESCE(tf.discount_amount, 0)
            ) as total_revenue,
            SUM(COALESCE(tf.cost_of_product, 0) + COALESCE(tf.transport_expenses, 0)) as total_trip_cost
        ')->first();

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
}
