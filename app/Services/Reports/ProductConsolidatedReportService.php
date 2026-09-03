<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductConsolidatedReportService implements ReportServiceInterface
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
            ->leftJoin('mm_patrons as dp', 'dp.id', '=', 'd.customer_id')
            ->leftJoin('mm_patrons as p', 'p.id', '=', 'so.customer_id')
            ->leftJoin('mm_sites as ds_site', 'ds_site.id', '=', 'd.unload_site_id')
            ->leftJoin('mm_sites as s', 's.id', '=', 'so.site_id')
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_personnels as drv', 'drv.id', '=', 'd.driver_id')
            ->leftJoin('mm_mix_designs as dm', 'dm.id', '=', 'd.mixdesign_id')
            ->leftJoin('mm_mix_designs as m', 'm.id', '=', 'so.mix_design_id')
            ->leftJoin('mm_concrete_grades as cg', 'cg.id', '=', DB::raw('COALESCE(dm.concrete_grade_id, m.concrete_grade_id)'))
            ->leftJoin('mm_product_units as u', 'u.id', '=', DB::raw('COALESCE(d.uom_id, dm.unit_id, m.unit_id)'))
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

        $patronId = $params['patron_id'] ?? null;
        $gradeId  = $params['grade_id'] ?? null;

        if ($patronId) {
            $query->where(function ($q) use ($patronId) {
                $q->where('d.customer_id', $patronId)
                  ->orWhere('so.customer_id', $patronId);
            });
        }

        if ($gradeId) {
            $query->where(function ($q) use ($gradeId) {
                $q->where('dm.concrete_grade_id', $gradeId)
                  ->orWhere('m.concrete_grade_id', $gradeId)
                  ->orWhere('cg.id', $gradeId);
            });
        }

        $dispatches = $query->select([
            'd.id',
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
            'd.unload_site_id',
            'd.payment_mode',
            'd.mixdesign_id',
            'b.id as batch_id',
            'b.batch_no',
            'b.batch_size',
            't.registration as truck_no',
            DB::raw('COALESCE(dp.legal_name, p.legal_name, "N/A") as customer_name'),
            DB::raw('TRIM(CONCAT(COALESCE(drv.first_name, ""), " ", COALESCE(drv.last_name, ""))) as driver_name'),
            DB::raw('COALESCE(ds_site.name, s.name, "N/A") as site_name'),
            DB::raw('COALESCE(dm.design_name, m.design_name) as mix_name'),
            DB::raw('COALESCE(cg.name, dm.design_type, m.design_type, "N/A") as concrete_grade'),
            DB::raw('COALESCE(u.unit_code, u.unit_name, "m³") as uom'),
        ])->get();

        // 1. Standard Product Consolidated (Mix Design & Concrete Grade wise)
        $consolidated = $dispatches->groupBy(function ($d) {
            return ($d->mixdesign_id ? 'md_' . $d->mixdesign_id : '') . '_' . ($d->mix_name ?: '') . '_' . ($d->concrete_grade ?: '');
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
                'mix_name'       => $first->mix_name ?: ($first->concrete_grade ?: 'Standard Mix'),
                'product_name'   => $first->mix_name ?: ($first->concrete_grade ?: 'Standard Mix'),
                'concrete_grade' => $first->concrete_grade ?? 'N/A',
                'uom'            => $first->uom ?? 'm³',
                'trips_count'    => $items->count(),
                'batch_size'     => $batchSize,
                'quantity'       => $totalQty,
                'truck_empty'    => $truckEmpty,
                'empty_weight'   => $truckEmpty,
                'loaded_weight'  => $loadedWeight,
                'netweight'      => $netWeight,
                'net_weight'     => $netWeight,
                'avg_rate'       => $totalQty > 0 ? $untaxed / $totalQty : 0.0,
                'amount_untaxed' => $untaxed,
                'amount_tax'     => $tax,
                'amount_total'   => $total,
            ];
        })->values()->sortBy('mix_name')->values();

        // 2. Unload Site based Product Consolidated (Mix Design & Site wise)
        $productSiteSummary = $dispatches->groupBy(function ($d) {
            return ($d->mix_name ?: '') . '_' . ($d->concrete_grade ?: '') . '_' . ($d->site_name ?: '');
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
                'mix_name'       => $first->mix_name ?: ($first->concrete_grade ?: 'Standard Mix'),
                'concrete_grade' => $first->concrete_grade ?? 'N/A',
                'site_name'      => $first->site_name ?: 'N/A',
                'uom'            => $first->uom ?? 'm³',
                'trips_count'    => $items->count(),
                'batch_size'     => $batchSize,
                'quantity'       => $totalQty,
                'truck_empty'    => $truckEmpty,
                'empty_weight'   => $truckEmpty,
                'loaded_weight'  => $loadedWeight,
                'netweight'      => $netWeight,
                'net_weight'     => $netWeight,
                'avg_rate'       => $totalQty > 0 ? $untaxed / $totalQty : 0.0,
                'amount_untaxed' => $untaxed,
                'amount_tax'     => $tax,
                'amount_total'   => $total,
            ];
        })->values()->sortBy('mix_name')->values();

        // 3. Unload Site Consolidated
        $siteSummary = $dispatches->groupBy(function ($d) {
            return $d->unload_site_id ?: ($d->site_name ?: 'Unknown Site');
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
                'site_name'      => $first->site_name ?: 'Unknown Site',
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
        })->values()->sortBy('site_name')->values();

        // 4. Payment Mode Consolidated
        $paymentModeSummary = $dispatches->groupBy(function ($d) {
            return !empty($d->payment_mode) ? strtolower(trim($d->payment_mode)) : 'unspecified';
        })->map(function ($items) {
            $first        = $items->first();
            $modeLabel    = !empty($first->payment_mode) ? ucfirst($first->payment_mode) : 'Not Specified';
            $totalQty     = (float)$items->sum('delivered_qty');
            $untaxed      = (float)$items->sum('load_untax_amount');
            $tax          = (float)$items->sum('load_tax_amount');
            $total        = (float)$items->sum('load_total_amount');
            $batchSize    = (float)$items->sum('batch_size');
            $truckEmpty   = (float)$items->sum('empty_weight_truck');
            $loadedWeight = (float)$items->sum('loaded_weight_truck');
            $netWeight    = (float)$items->sum('net_weight');

            return [
                'payment_mode'   => $modeLabel,
                'mode_name'      => $modeLabel,
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
        })->values()->sortBy('payment_mode')->values();

        // 5. Detailed itemized batching / trip list
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
                'mix_name'       => $d->mix_name ?: ($d->concrete_grade ?: 'Standard Mix'),
                'concrete_grade' => $d->concrete_grade ?? 'N/A',
                'customer_name'  => $d->customer_name ?: 'N/A',
                'site_name'      => $d->site_name ?: 'N/A',
                'truck_no'       => $d->truck_no ?: 'N/A',
                'driver_name'    => $d->driver_name ?: 'N/A',
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
            'opening_balance'      => 0,
            'transactions'         => $consolidated,
            'items'                => $consolidated,
            'batch_dispatches'     => $batchDispatches,
            'product_site_summary' => $productSiteSummary,
            'site_summary'         => $siteSummary,
            'payment_mode_summary' => $paymentModeSummary,
            'total_trips'          => (int)$consolidated->sum('trips_count'),
            'total_batch_size'     => (float)$consolidated->sum('batch_size'),
            'total_quantity'       => (float)$consolidated->sum('quantity'),
            'total_truck_empty'    => (float)$consolidated->sum('truck_empty'),
            'total_loaded_weight'  => (float)$consolidated->sum('loaded_weight'),
            'total_net_weight'     => (float)$consolidated->sum('netweight'),
            'total_untaxed'        => (float)$consolidated->sum('amount_untaxed'),
            'total_tax'            => (float)$consolidated->sum('amount_tax'),
            'total_amount'         => (float)$consolidated->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        $parts = [];
        if (!empty($params['patron_id'])) {
            $patron = \App\Models\Patron::find($params['patron_id']);
            if ($patron) $parts[] = 'Customer: ' . $patron->legal_name;
        }
        if (!empty($params['grade_id'])) {
            $grade = \App\Models\ConcreteGrade::find($params['grade_id']);
            if ($grade) $parts[] = 'Grade: ' . $grade->name;
        }
        return !empty($parts) 
            ? implode(' | ', $parts)
            : 'All Products & Concrete Grades';
    }
}
