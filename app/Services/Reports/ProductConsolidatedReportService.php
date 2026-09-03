<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
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
            ->leftJoin('mm_sites as ds_site', 'ds_site.id', '=', 'd.unload_site_id')
            ->leftJoin('mm_sites as s', 's.id', '=', 'so.site_id')
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

        $dispatches = $query->select([
            'd.id',
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
            'b.batch_size',
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

        return [
            'opening_balance'      => 0,
            'transactions'         => $consolidated,
            'items'                => $consolidated,
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
        return 'Product Consolidated Report (Mix Design & Concrete Grade wise)';
    }
}
