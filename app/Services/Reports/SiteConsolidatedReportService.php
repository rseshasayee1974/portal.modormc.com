<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SiteConsolidatedReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $query = DB::table('mm_dispatches as d')
            ->leftJoin('mm_batches as b', 'b.id', '=', 'd.batch_id')
            ->leftJoin('mm_sales_orders as so', 'so.id', '=', 'd.sales_order_id')
            ->leftJoin('mm_patrons as p', 'p.id', '=', DB::raw('COALESCE(NULLIF(d.customer_id, 0), so.customer_id)'))
            ->leftJoin('mm_sites as s', 's.id', '=', DB::raw('COALESCE(NULLIF(d.unload_site_id, 0), so.site_id)'))
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_personnels as drv', 'drv.id', '=', 'd.driver_id')
            ->leftJoin('mm_mix_designs as m', 'm.id', '=', DB::raw('COALESCE(NULLIF(d.mixdesign_id, 0), so.mix_design_id)'))
            ->leftJoin('mm_concrete_grades as cg', 'cg.id', '=', 'm.concrete_grade_id')
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

        $truckId  = $params['truck_id'] ?? null;
        $patronId = $params['patron_id'] ?? null;

        if (!empty($params['mix_design_id'])) {
            $query->whereRaw('COALESCE(NULLIF(d.mixdesign_id, 0), so.mix_design_id) = ?', [$params['mix_design_id']]);
        }

        if ($truckId) {
            $query->where('d.truck_id', $truckId);
        }

        if ($patronId) {
            $query->whereRaw('COALESCE(NULLIF(d.customer_id, 0), so.customer_id) = ?', [$patronId]);
        }

        if (!empty($params['site_id'])) {
            $query->whereRaw('COALESCE(NULLIF(d.unload_site_id, 0), so.site_id) = ?', [$params['site_id']]);
        }

        $dispatches = $query->select([
            'd.id',
            'd.prefix as dispatch_prefix',
            'd.dispatch_no',
            'd.dispatch_reference',
            'd.dispatch_time',
            'd.created_at',
            DB::raw('COALESCE(d.delivered_qty, b.batch_size, 0) as delivered_qty'),
            'd.load_rate',
            'd.load_untax_amount',
            'd.load_tax_amount',
            'd.load_total_amount',
            'd.empty_weight_truck',
            'd.loaded_weight_truck',
            'd.net_weight',
            DB::raw('COALESCE(NULLIF(d.unload_site_id, 0), so.site_id) as unload_site_id'),
            DB::raw('COALESCE(NULLIF(d.customer_id, 0), so.customer_id) as customer_id'),
            DB::raw('COALESCE(NULLIF(d.mixdesign_id, 0), so.mix_design_id) as mix_design_id'),
            'b.id as batch_id',
            'b.batch_no',
            'b.batch_size',
            't.registration as truck_no',
            DB::raw('COALESCE(p.legal_name, "N/A") as customer_name'),
            DB::raw('TRIM(CONCAT(COALESCE(drv.first_name, ""), " ", COALESCE(drv.last_name, ""))) as driver_name'),
            DB::raw('COALESCE(s.name, "Unknown Site") as site_name'),
            DB::raw('COALESCE(NULLIF(m.design_name, ""), cg.name, "Unknown Mix") as mix_name'),
            DB::raw('COALESCE(cg.name, m.design_type, "Standard Grade") as concrete_grade'),
        ])->get();

        $consolidated = $dispatches->groupBy(function ($d) {
            return implode(':', [$d->unload_site_id, $d->customer_id, $d->mix_design_id]);
        })->map(function ($items) {
            $first        = $items->first();
            $totalQty     = (float)$items->sum('delivered_qty');
            $untaxed      = (float)$items->sum('load_untax_amount');
            $tax          = (float)$items->sum('load_tax_amount');
            $total        = (float)$items->sum('load_total_amount');
            $batchSize    = (float)$items->sum('batch_size');
            $truckEmpty   = (float)$items->sum('empty_weight_truck');
            $loadedWeight = (float)$items->sum('loaded_weight_truck');
            $netWeight    = (float)$items->sum('net_weight');

            return [
                'site_id'        => $first->unload_site_id ?? null,
                'site_name'      => $first->site_name ?: 'Unknown Site',
                'customer_name'  => $first->customer_name ?: '-',
                'mix_name'       => $first->mix_name,
                'trips_count'    => $items->count(),
                'batch_size'     => $batchSize,
                'quantity'       => $totalQty,
                'truck_empty'    => $truckEmpty,
                'empty_weight'   => $truckEmpty,
                'loaded_weight'  => $loadedWeight,
                'netweight'      => $netWeight,
                'net_weight'     => $netWeight,
                'amount_untaxed' => $untaxed,
                'amount_tax'     => $tax,
                'amount_total'   => $total,
            ];
        })->sortBy([['site_name', 'asc'], ['customer_name', 'asc'], ['mix_name', 'asc']])->values();

        // Detailed itemized batching / trip list
        $batchDispatches = $dispatches->map(function ($d, $idx) {
            $dispatchNumber = trim(($d->dispatch_prefix ?? '') . ' ' . ($d->dispatch_no ?? $d->dispatch_reference ?? ('DSP-' . $d->id)));
            $dispatchTime = $d->dispatch_time 
                ? Carbon::parse($d->dispatch_time)->format('d/m/Y h:i A') 
                : ($d->created_at ? Carbon::parse($d->created_at)->format('d/m/Y h:i A') : '-');

            return [
                'index'          => $idx + 1,
                'dispatch_id'    => $d->id,
                'dispatch_no'    => $dispatchNumber,
                'docket_no'      => $dispatchNumber,
                'batch_id'       => $d->batch_id ?? null,
                'batch_no'       => $d->batch_no ?? ('B-' . $d->id),
                'dispatch_time'  => $dispatchTime,
                'site_name'      => $d->site_name ?: 'Unknown Site',
                'customer_name'  => $d->customer_name ?: 'N/A',
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

        return [
            'opening_balance'     => 0,
            'transactions'        => $consolidated,
            'items'               => $consolidated,
            'batch_dispatches'    => $batchDispatches,
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
            : 'All Unload & Delivery Sites';
    }
}
