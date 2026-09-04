<?php

namespace App\Services\Reports;

use App\Models\Machine;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TruckConsolidatedReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $truckId = $params['truck_id'] ?? null;
        $start   = $params['start'];
        $end     = $params['end'];

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

        if ($truckId) {
            $query->where('d.truck_id', $truckId);
        }

        $patronId = $params['patron_id'] ?? null;
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
            'd.created_at',
            'd.delivered_qty',
            'd.load_rate',
            'd.load_untax_amount',
            'd.load_tax_amount',
            'd.load_total_amount',
            'd.empty_weight_truck',
            'd.loaded_weight_truck',
            'd.net_weight',
            'd.truck_id',
            'd.unload_site_id',
            'b.id as batch_id',
            'b.batch_no',
            'b.batch_size',
            't.registration as truck_no',
            DB::raw('COALESCE(dp.legal_name, p.legal_name, "Unknown Customer") as customer_name'),
            DB::raw('COALESCE(ds_site.name, s.name, "Plant Site") as site_name'),
            DB::raw('COALESCE(dm.design_name, m.design_name, "Standard Mix") as mix_name'),
            DB::raw('COALESCE(cg.name, dm.design_type, m.design_type, "Standard Grade") as concrete_grade'),
            DB::raw('TRIM(CONCAT(COALESCE(drv.first_name, ""), " ", COALESCE(drv.last_name, ""))) as driver_name'),
        ])
        ->orderBy('t.registration', 'asc')
        ->orderBy('d.dispatch_time', 'asc')
        ->orderBy('d.id', 'asc')
        ->get();

        // Every truck-wise trip record with all requested columns
        $truckTrips = $dispatches->map(function ($d, $idx) {
            $dispatchNumber = trim(($d->dispatch_prefix ?? '') . ' ' . ($d->dispatch_no ?? $d->dispatch_reference ?? ('DSP-' . $d->dispatch_id)));
            $dispatchTime = $d->dispatch_time 
                ? Carbon::parse($d->dispatch_time)->format('d/m/Y h:i A') 
                : ($d->created_at ? Carbon::parse($d->created_at)->format('d/m/Y h:i A') : '-');

            return [
                'index'          => $idx + 1,
                'dispatch_id'    => $d->dispatch_id,
                'dispatch_no'    => $dispatchNumber,
                'docket_no'      => $dispatchNumber,
                'batch_id'       => $d->batch_id,
                'batch_no'       => $d->batch_no ?: ('B-' . $d->batch_id),
                'dispatch_time'  => $dispatchTime,
                'truck_no'       => $d->truck_no ?: 'Unknown Truck',
                'truck_id'       => $d->truck_id,
                'customer_name'  => $d->customer_name,
                'site_name'      => $d->site_name,
                'driver_name'    => $d->driver_name ?: 'N/A',
                'mix_name'       => $d->mix_name,
                'concrete_grade' => $d->concrete_grade,
                'batch_size'     => (float)($d->batch_size ?? 0),
                'delivered_qty'  => (float)($d->delivered_qty ?? 0),
                'empty_weight'   => (float)($d->empty_weight_truck ?? 0),
                'loaded_weight'  => (float)($d->loaded_weight_truck ?? 0),
                'net_weight'     => (float)($d->net_weight ?? 0),
                'rate'           => (float)($d->load_rate ?? 0),
                'amount_untaxed' => (float)($d->load_untax_amount ?? 0),
                'amount_tax'     => (float)($d->load_tax_amount ?? 0),
                'amount_total'   => (float)($d->load_total_amount ?? 0),
            ];
        })->values();

        // Grouped by truck for truck-wise breakdown view
        $truckGroups = $truckTrips->groupBy('truck_no')->map(function ($trips, $truckNo) {
            return [
                'truck_no'       => $truckNo,
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
        })->values()->sortBy('truck_no')->values();

        return [
            'opening_balance'     => 0,
            'truck_trips'         => $truckTrips->all(),
            'truck_groups'        => $truckGroups->all(),
            'transactions'        => $truckTrips->all(),
            'items'               => $truckTrips->all(),
            'total_trips'         => (int)$truckTrips->count(),
            'total_batch_size'    => (float)$truckTrips->sum('batch_size'),
            'total_quantity'      => (float)$truckTrips->sum('delivered_qty'),
            'total_truck_empty'   => (float)$truckTrips->sum('empty_weight'),
            'total_loaded_weight' => (float)$truckTrips->sum('loaded_weight'),
            'total_net_weight'    => (float)$truckTrips->sum('net_weight'),
            'total_untaxed'       => (float)$truckTrips->sum('amount_untaxed'),
            'total_tax'           => (float)$truckTrips->sum('amount_tax'),
            'total_amount'        => (float)$truckTrips->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        $parts = [];
        if (!empty($params['truck_id'])) {
            $truck = \App\Models\Machine::whereNull('deleted_at')->find($params['truck_id']);
            if ($truck) $parts[] = 'Truck: ' . $truck->registration;
        }
        if (!empty($params['patron_id'])) {
            $patron = \App\Models\Patron::find($params['patron_id']);
            if ($patron) $parts[] = 'Customer: ' . $patron->legal_name;
        }
        return !empty($parts)
            ? implode(' | ', $parts)
            : 'All Trucks - Truck Wise Trip Report';
    }
}
