<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use Illuminate\Support\Facades\DB;

class PaymentModeConsolidatedReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $query = DB::table('mm_dispatches as d')
            ->leftJoin('mm_batches as b', 'b.id', '=', 'd.batch_id')
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
            'd.payment_mode',
            'b.batch_size',
        ])->get();

        $consolidated = $dispatches->groupBy(function ($d) {
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
            'opening_balance'     => 0,
            'transactions'        => $consolidated,
            'items'               => $consolidated,
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
        return 'Payment Mode Consolidated Report';
    }
}
