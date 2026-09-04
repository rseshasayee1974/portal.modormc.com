<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesExecutiveReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId          = $this->ctx->requirePlantId();
        $salesExecutiveId = $params['sales_executive_id'] ?? $params['id'] ?? null;
        $patronId         = $params['patron_id'] ?? null;
        $start            = $params['start'];
        $end              = $params['end'];

        $query = DB::table('mm_dispatches as d')
            ->leftJoin('mm_personnels as se', 'se.id', '=', 'd.sales_executive_id')
            ->leftJoin('mm_personnels as drv', 'drv.id', '=', 'd.driver_id')
            ->leftJoin('mm_batches as b', 'b.id', '=', 'd.batch_id')
            ->leftJoin('mm_sales_orders as so', 'so.id', '=', 'd.sales_order_id')
            ->leftJoin('mm_patrons as dp', 'dp.id', '=', 'd.customer_id')
            ->leftJoin('mm_patrons as p', 'p.id', '=', 'so.customer_id')
            ->leftJoin('mm_sites as ds_site', 'ds_site.id', '=', 'd.unload_site_id')
            ->leftJoin('mm_sites as s', 's.id', '=', 'so.site_id')
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_mix_designs as dm', 'dm.id', '=', 'd.mixdesign_id')
            ->leftJoin('mm_mix_designs as m', 'm.id', '=', 'so.mix_design_id')
            ->leftJoin('mm_concrete_grades as cg', 'cg.id', '=', DB::raw('COALESCE(dm.concrete_grade_id, m.concrete_grade_id)'))
            ->leftJoin('mm_invoices as inv', function ($join) {
                $join->on('inv.id', '=', DB::raw('(SELECT invoice_id FROM mm_dispatch_statuses WHERE dispatch_id = d.id AND deleted_at IS NULL LIMIT 1)'))
                     ->whereNull('inv.deleted_at');
            })
            ->where('d.plant_id', $plantId)
            ->whereNull('d.deleted_at')
            ->where(function ($q) {
                $q->whereNull('d.dispatch_status')
                  ->orWhere('d.dispatch_status', '!=', 'Cancelled');
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('d.dispatch_time', [$start, $end])
                  ->orWhereBetween('inv.invoice_date', [$start, $end])
                  ->orWhere(function ($sq) use ($start, $end) {
                      $sq->whereNull('d.dispatch_time')
                         ->whereBetween('d.created_at', [$start, $end]);
                  });
            });

        if ($salesExecutiveId) {
            $query->where('d.sales_executive_id', $salesExecutiveId);
        }

        if ($patronId) {
            $query->where(function ($q) use ($patronId) {
                $q->where('d.customer_id', $patronId)
                  ->orWhere('so.customer_id', $patronId);
            });
        }

        $dispatches = $query->select([
            'd.id as dispatch_id',
            'd.prefix as dispatch_prefix',
            'd.dispatch_no',
            'd.dispatch_reference',
            'd.dispatch_time',
            'd.delivered_qty',
            'd.load_rate',
            'd.load_untax_amount',
            'd.load_tax_amount',
            'd.load_total_amount',
            'd.empty_weight_truck',
            'd.loaded_weight_truck',
            'd.net_weight',
            'd.payment_mode',
            'd.sales_executive_id',
            'b.batch_size',
            'b.shift',
            DB::raw('COALESCE(se.full_name, TRIM(CONCAT(COALESCE(se.first_name, ""), " ", COALESCE(se.last_name, ""))), "Unassigned") as sales_executive_name'),
            'se.employee_code as executive_code',
            'se.mobile as executive_mobile',
            DB::raw('COALESCE(dp.legal_name, p.legal_name, "N/A") as customer_name'),
            DB::raw('COALESCE(ds_site.name, s.name, "N/A") as site_name'),
            DB::raw('COALESCE(dm.design_name, m.design_name, "N/A") as mix_name'),
            DB::raw('COALESCE(cg.name, dm.design_type, m.design_type, "N/A") as concrete_grade'),
            't.registration as truck_no',
            DB::raw('COALESCE(drv.full_name, TRIM(CONCAT(COALESCE(drv.first_name, ""), " ", COALESCE(drv.last_name, ""))), "N/A") as driver_name'),
            'inv.prefix as invoice_prefix',
            'inv.invoice_number',
            'inv.invoice_date',
        ])
        ->orderByDesc('d.dispatch_time')
        ->orderByDesc('d.id')
        ->get();

        // 1. Executive Consolidated Summary
        $consolidated = $dispatches->groupBy(function ($d) {
            return $d->sales_executive_id ?: 'unassigned';
        })->map(function ($items) {
            $first        = $items->first();
            $execName     = $first->sales_executive_name ?: 'Unassigned';
            $totalQty     = (float)$items->sum('delivered_qty');
            $untaxed      = (float)$items->sum('load_untax_amount');
            $tax          = (float)$items->sum('load_tax_amount');
            $total        = (float)$items->sum('load_total_amount');
            $batchSize    = (float)$items->sum('batch_size');
            $truckEmpty   = (float)$items->sum('empty_weight_truck');
            $loadedWeight = (float)$items->sum('loaded_weight_truck');
            $netWeight    = (float)$items->sum('net_weight');

            return [
                'sales_executive_id'   => $first->sales_executive_id,
                'sales_executive_name' => $execName,
                'executive_name'       => $execName,
                'executive_code'       => $first->executive_code ?? '',
                'executive_mobile'     => $first->executive_mobile ?? '',
                'trips_count'          => $items->count(),
                'batch_size'           => $batchSize,
                'quantity'             => $totalQty,
                'truck_empty'          => $truckEmpty,
                'empty_weight'         => $truckEmpty,
                'loaded_weight'        => $loadedWeight,
                'netweight'            => $netWeight,
                'net_weight'           => $netWeight,
                'amount_untaxed'       => $untaxed,
                'amount_tax'           => $tax,
                'amount_total'         => $total,
            ];
        })->values()->sortByDesc('amount_total')->values();

        // 2. Executive + Customer Breakdown
        $executiveCustomerSummary = $dispatches->groupBy(function ($d) {
            return ($d->sales_executive_name ?: 'Unassigned') . '___' . ($d->customer_name ?: 'Unknown');
        })->map(function ($items) {
            $first = $items->first();
            return [
                'sales_executive_name' => $first->sales_executive_name ?: 'Unassigned',
                'customer_name'        => $first->customer_name ?: 'N/A',
                'trips_count'          => $items->count(),
                'quantity'             => (float)$items->sum('delivered_qty'),
                'batch_size'           => (float)$items->sum('batch_size'),
                'net_weight'           => (float)$items->sum('net_weight'),
                'amount_untaxed'       => (float)$items->sum('load_untax_amount'),
                'amount_total'         => (float)$items->sum('load_total_amount'),
            ];
        })->values()->sortBy('sales_executive_name')->values();

        // 3. Transactions List
        $transactions = $dispatches->map(function ($row) {
            $dispatchDate = $row->dispatch_time ? Carbon::parse($row->dispatch_time) : now();
            $invNum = !empty($row->invoice_number) ? (($row->invoice_prefix ?? '') . $row->invoice_number) : '-';
            $dispatchNo = ($row->dispatch_prefix ?? '') . ($row->dispatch_no ?? $row->dispatch_id);

            return [
                'dispatch_id'          => $row->dispatch_id,
                'dispatch_no'          => $dispatchNo,
                'date'                 => $dispatchDate->format('d-M-Y'),
                'time'                 => $dispatchDate->format('h:i A'),
                'datetime'             => $dispatchDate->format('d-M-Y h:i A'),
                'invoice_number'       => $invNum,
                'invoice_date'         => $row->invoice_date ? Carbon::parse($row->invoice_date)->format('d-M-Y') : '-',
                'sales_executive_name' => $row->sales_executive_name,
                'customer_name'        => $row->customer_name,
                'site_name'            => $row->site_name,
                'mix_name'             => $row->mix_name,
                'concrete_grade'       => $row->concrete_grade,
                'truck_no'             => $row->truck_no ?: 'N/A',
                'driver_name'          => $row->driver_name ?: 'N/A',
                'quantity'             => (float)$row->delivered_qty,
                'batch_size'           => (float)$row->batch_size,
                'empty_weight'         => (float)$row->empty_weight_truck,
                'truck_empty'          => (float)$row->empty_weight_truck,
                'loaded_weight'        => (float)$row->loaded_weight_truck,
                'net_weight'           => (float)$row->net_weight,
                'netweight'            => (float)$row->net_weight,
                'amount_untaxed'       => (float)$row->load_untax_amount,
                'amount_tax'           => (float)$row->load_tax_amount,
                'amount_total'         => (float)$row->load_total_amount,
                'payment_mode'         => $row->payment_mode ?: '-',
            ];
        });

        // 4. Totals
        $totals = [
            'trips_count'    => $consolidated->sum('trips_count'),
            'batch_size'     => (float)$consolidated->sum('batch_size'),
            'total_quantity' => (float)$consolidated->sum('quantity'),
            'quantity'       => (float)$consolidated->sum('quantity'),
            'truck_empty'    => (float)$consolidated->sum('truck_empty'),
            'empty_weight'   => (float)$consolidated->sum('truck_empty'),
            'loaded_weight'  => (float)$consolidated->sum('loaded_weight'),
            'netweight'      => (float)$consolidated->sum('netweight'),
            'net_weight'     => (float)$consolidated->sum('netweight'),
            'amount_untaxed' => (float)$consolidated->sum('amount_untaxed'),
            'amount_tax'     => (float)$consolidated->sum('amount_tax'),
            'total_amount'   => (float)$consolidated->sum('amount_total'),
            'amount_total'   => (float)$consolidated->sum('amount_total'),
        ];

        return [
            'consolidated'               => $consolidated,
            'executive_customer_summary' => $executiveCustomerSummary,
            'transactions'               => $transactions,
            'totals'                     => $totals,
            'total_amount'               => $totals['total_amount'],
            'total_quantity'             => $totals['total_quantity'],
        ];
    }

    public function targetName(array $params): string
    {
        $id = $params['sales_executive_id'] ?? $params['id'] ?? null;
        if ($id) {
            $exec = \App\Models\Personnel::whereNull('deleted_at')->find($id);
            return $exec ? trim($exec->first_name . ' ' . $exec->last_name) : 'Sales Executive Report';
        }
        return 'All Sales Executives Consolidated';
    }
}
