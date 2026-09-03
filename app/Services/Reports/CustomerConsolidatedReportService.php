<?php

namespace App\Services\Reports;

use App\Models\Patron;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerConsolidatedReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];

        $query = DB::table('mm_dispatches as d')
            ->leftJoin('mm_batches as b', 'b.id', '=', 'd.batch_id')
            ->leftJoin('mm_sales_orders as so', 'so.id', '=', 'd.sales_order_id')
            ->leftJoin('mm_patrons as dp', 'dp.id', '=', 'd.customer_id')
            ->leftJoin('mm_patrons as p', 'p.id', '=', 'so.customer_id')
            ->leftJoin('mm_sites as ds_site', 'ds_site.id', '=', 'd.unload_site_id')
            ->leftJoin('mm_sites as s', 's.id', '=', 'so.site_id')
            ->leftJoin('mm_mix_designs as dm', 'dm.id', '=', 'd.mixdesign_id')
            ->leftJoin('mm_mix_designs as m', 'm.id', '=', 'so.mix_design_id')
            ->leftJoin('mm_concrete_grades as cg', 'cg.id', '=', DB::raw('COALESCE(dm.concrete_grade_id, m.concrete_grade_id)'))
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_personnels as drv', 'drv.id', '=', 'd.driver_id')
            ->leftJoin('mm_invoices as inv', function ($join) {
                $join->on('inv.id', '=', DB::raw('(SELECT invoice_id FROM mm_dispatch_statuses WHERE dispatch_id = d.id AND deleted_at IS NULL LIMIT 1)'))
                     ->whereNull('inv.deleted_at');
            })
            ->where('d.plant_id', $plantId)
            ->whereNull('d.deleted_at')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('d.dispatch_time', [$start, $end])
                  ->orWhereBetween('inv.invoice_date', [$start, $end])
                  ->orWhere(function ($sq) use ($start, $end) {
                      $sq->whereNull('d.dispatch_time')
                         ->whereBetween('d.created_at', [$start, $end]);
                  });
            });

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
            'd.load_time',
            'd.created_at',
            'd.delivered_qty',
            'd.load_rate',
            'd.load_untax_amount',
            'd.load_tax_amount',
            'd.load_total_amount',
            'd.empty_weight_truck',
            'd.loaded_weight_truck',
            'd.net_weight',
            'd.customer_id',
            'b.id as batch_id',
            'b.batch_no',
            'b.batch_size',
            DB::raw('COALESCE(NULLIF(TRIM(dp.legal_name), ""), NULLIF(TRIM(p.legal_name), ""), "Unknown Customer") as customer_name'),
            DB::raw('COALESCE(NULLIF(TRIM(ds_site.name), ""), NULLIF(TRIM(s.name), ""), "Plant Site") as site_name'),
            DB::raw('COALESCE(NULLIF(TRIM(dm.design_name), ""), NULLIF(TRIM(m.design_name), ""), "Standard Mix") as mix_name'),
            DB::raw('COALESCE(NULLIF(TRIM(cg.name), ""), NULLIF(TRIM(dm.design_type), ""), NULLIF(TRIM(m.design_type), ""), "Standard Grade") as concrete_grade'),
            't.registration as truck_no',
            DB::raw('TRIM(CONCAT(COALESCE(drv.first_name, ""), " ", COALESCE(drv.last_name, ""))) as driver_name'),
        ])
        ->orderByRaw('COALESCE(d.dispatch_time, d.load_time, d.created_at) ASC')
        ->orderBy('d.id', 'ASC')
        ->get();

        // 1. Detailed itemized batching / trip list
        $batchDispatches = $dispatches->map(function ($d, $idx) {
            $dispatchNumber = trim(($d->dispatch_prefix ?? '') . ' ' . ($d->dispatch_no ?? $d->dispatch_reference ?? ('DSP-' . ($d->dispatch_id ?? ''))));
            $rawLoadTime = $d->load_time ?? $d->dispatch_time ?? $d->created_at ?? null;
            $loadTimeFormatted = $rawLoadTime ? Carbon::parse($rawLoadTime)->format('d/m/Y h:i A') : '-';

            $custName = !empty(trim((string)$d->customer_name)) ? trim((string)$d->customer_name) : 'Unknown Customer';

            return [
                'index'              => $idx + 1,
                'dispatch_id'        => $d->dispatch_id,
                'dispatch_timestamp' => $rawLoadTime ? Carbon::parse($rawLoadTime)->timestamp : 0,
                'dispatch_no'        => $dispatchNumber,
                'docket_no'          => $dispatchNumber,
                'batch_id'           => $d->batch_id,
                'batch_no'           => $d->batch_no ?: ('B-' . $d->batch_id),
                'dispatch_time'      => $loadTimeFormatted,
                'load_time'          => $loadTimeFormatted,
                'customer_name'  => $custName,
                'party_name'     => $custName,
                'customer_id'    => $d->customer_id,
                'site_name'      => $d->site_name,
                'truck_no'       => $d->truck_no ?: 'N/A',
                'driver_name'    => $d->driver_name ?: 'N/A',
                'mix_name'       => $d->mix_name,
                'concrete_grade' => $d->concrete_grade,
                'batch_size'     => (float)($d->batch_size ?? 0),
                'delivered_qty'  => (float)($d->delivered_qty ?? 0),
                'quantity'       => (float)($d->delivered_qty ?? 0),
                'empty_weight'   => (float)($d->empty_weight_truck ?? 0),
                'loaded_weight'  => (float)($d->loaded_weight_truck ?? 0),
                'net_weight'     => (float)($d->net_weight ?? 0),
                'rate'           => (float)($d->load_rate ?? 0),
                'amount_untaxed' => (float)($d->load_untax_amount ?? 0),
                'amount_tax'     => (float)($d->load_tax_amount ?? 0),
                'amount_total'   => (float)($d->load_total_amount ?? 0),
            ];
        })->values();

        // 2. Dispatches grouped per customer for trip verification
        $customerBatches = $batchDispatches->groupBy(function ($d) {
            return $d['customer_id'] ?: $d['customer_name'];
        })->map(function ($trips) {
            $first = $trips->first();
            $name = trim((string)($first['customer_name'] ?? ''));
            if ($name === '' || $name === 'Unknown Customer') {
                foreach ($trips as $t) {
                    if (!empty(trim((string)($t['customer_name'] ?? ''))) && $t['customer_name'] !== 'Unknown Customer') {
                        $name = trim((string)$t['customer_name']);
                        break;
                    }
                }
            }
            if ($name === '') {
                $name = 'Unknown Customer';
            }

            return [
                'customer_name'  => $name,
                'party_name'     => $name,
                'customer_id'    => $first['customer_id'],
                'trips_count'    => $trips->count(),
                'total_batch'    => (float)$trips->sum('batch_size'),
                'total_qty'      => (float)$trips->sum('delivered_qty'),
                'total_empty'    => (float)$trips->sum('empty_weight'),
                'total_loaded'   => (float)$trips->sum('loaded_weight'),
                'total_net'      => (float)$trips->sum('net_weight'),
                'total_untaxed'  => (float)$trips->sum('amount_untaxed'),
                'total_tax'      => (float)$trips->sum('amount_tax'),
                'total_amount'   => (float)$trips->sum('amount_total'),
                'trips'          => $trips->values()->all(),
            ];
        })->values()->sortBy('customer_name')->values();

        // 3. Consolidated customer summary table
        $consolidated = $customerBatches->map(function ($c) {
            $name = !empty(trim((string)($c['party_name'] ?? $c['customer_name'] ?? ''))) 
                ? trim((string)($c['party_name'] ?? $c['customer_name'])) 
                : 'Unknown Customer';

            return [
                'party_name'     => $name,
                'customer_name'  => $name,
                'customer_id'    => $c['customer_id'],
                'trips_count'    => $c['trips_count'],
                'batch_size'     => $c['total_batch'],
                'quantity'       => $c['total_qty'],
                'delivered_qty'  => $c['total_qty'],
                'truck_empty'    => $c['total_empty'],
                'empty_weight'   => $c['total_empty'],
                'loaded_weight'  => $c['total_loaded'],
                'netweight'      => $c['total_net'],
                'net_weight'     => $c['total_net'],
                'amount_untaxed' => $c['total_untaxed'],
                'taxable_amount' => $c['total_untaxed'],
                'amount_tax'     => $c['total_tax'],
                'tax_amount'     => $c['total_tax'],
                'amount_total'   => $c['total_amount'],
                'total_amount'   => $c['total_amount'],
            ];
        })->values();

        return [
            'opening_balance'     => 0,
            'transactions'        => $consolidated,
            'items'               => $consolidated,
            'batch_dispatches'    => $batchDispatches->all(),
            'customer_batches'    => $customerBatches->all(),
            'total_trips'         => (int)$consolidated->sum('trips_count'),
            'total_batch_size'    => (float)$consolidated->sum('batch_size'),
            'total_quantity'      => (float)$consolidated->sum('quantity'),
            'total_truck_empty'   => (float)$consolidated->sum('truck_empty'),
            'total_loaded_weight' => (float)$consolidated->sum('loaded_weight'),
            'total_net_weight'    => (float)$consolidated->sum('netweight'),
            'total_untaxed'       => (float)$consolidated->sum('amount_untaxed'),
            'total_tax'           => (float)$consolidated->sum('amount_tax'),
            'total_amount'        => (float)$consolidated->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id'])
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Customer Consolidated Report')
            : 'All Customers Consolidated Report';
    }
}
