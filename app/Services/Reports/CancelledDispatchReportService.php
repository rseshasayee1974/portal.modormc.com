<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;

class CancelledDispatchReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $start    = $params['start'];
        $end      = $params['end'];
        $patronId = $params['patron_id'] ?? null;

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
            ->leftJoin('mm_users as u', 'u.id', '=', 'd.cancelled_by')
            ->leftJoin('mm_dispatch_statuses as ds', function ($join) {
                $join->on('ds.dispatch_id', '=', 'd.id')
                     ->whereNull('ds.deleted_at');
            })
            ->leftJoin('mm_invoices as inv', function ($join) {
                $join->on('inv.id', '=', 'ds.invoice_id')
                     ->whereNull('inv.deleted_at');
            })
            ->leftJoin('mm_invoices as cn', function ($join) {
                $join->on('cn.ref_id', '=', 'inv.id')
                     ->where('cn.invoice_type', '=', 'credit_note')
                     ->whereNull('cn.deleted_at');
            })
            ->where('d.plant_id', $plantId)
            ->whereNull('d.deleted_at')
            ->where(function ($q) {
                $q->where('d.dispatch_status', 'Cancelled')
                  ->orWhere('b.status', Batch::STATUS_CANCELLED);
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('d.cancelled_at', [$start, $end])
                  ->orWhereBetween('d.dispatch_time', [$start, $end])
                  ->orWhereBetween('d.created_at', [$start, $end]);
            });

        if ($patronId) {
            $query->where(function ($q) use ($patronId) {
                $q->where('d.customer_id', $patronId)
                  ->orWhere('so.customer_id', $patronId);
            });
        }

        $records = $query->select([
            'd.id as dispatch_id',
            'd.prefix as dispatch_prefix',
            'd.dispatch_no',
            'd.dispatch_time',
            'd.created_at',
            'd.cancelled_at',
            'd.cancelled_notes',
            'd.delivered_qty',
            'd.load_rate',
            'd.load_untax_amount',
            'd.load_tax_amount',
            'd.load_total_amount',
            'b.id as batch_id',
            'b.batch_no',
            'b.batch_size',
            'so.prefix as so_prefix',
            'so.order_no as so_number',
            DB::raw("COALESCE(dp.legal_name, p.legal_name, 'Unknown Customer') as customer_name"),
            DB::raw("COALESCE(ds_site.name, s.name, 'N/A') as site_name"),
            DB::raw("COALESCE(cg.name, 'Ready-Mix Concrete') as grade_name"),
            't.registration as truck_no',
            DB::raw("TRIM(CONCAT(COALESCE(drv.first_name, ''), ' ', COALESCE(drv.last_name, ''))) as driver_name"),
            'u.username as cancelled_by_name',
            'inv.invoice_number',
            'cn.prefix as cn_prefix',
            'cn.invoice_number as cn_number',
        ])
        ->orderByDesc(DB::raw('COALESCE(d.cancelled_at, d.created_at)'))
        ->get()
        ->map(function ($row) {
            $dt = $row->dispatch_time ? \Carbon\Carbon::parse($row->dispatch_time)->format('d-m-Y H:i') : ($row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d-m-Y H:i') : 'N/A');
            $cnDt = $row->cancelled_at ? \Carbon\Carbon::parse($row->cancelled_at)->format('d-m-Y H:i') : 'N/A';

            return [
                'dispatch_id'         => $row->dispatch_id,
                'dispatch_no'         => ($row->dispatch_prefix ?? '') . $row->dispatch_no,
                'dispatch_date'       => $dt,
                'cancelled_at'        => $cnDt,
                'cancelled_by'        => $row->cancelled_by_name ?: 'System',
                'cancelled_notes'     => $row->cancelled_notes ?: 'No cancellation notes recorded.',
                'batch_no'            => $row->batch_no ? ('B' . $row->batch_no) : 'N/A',
                'batch_size'          => (float)($row->batch_size ?? 0),
                'sales_order_no'      => ($row->so_prefix ?? '') . ($row->so_number ?? ''),
                'customer_name'       => $row->customer_name,
                'site_name'           => $row->site_name,
                'grade_name'          => $row->grade_name,
                'truck_no'            => $row->truck_no ?: 'N/A',
                'driver_name'         => $row->driver_name ?: 'N/A',
                'quantity'            => (float)($row->delivered_qty > 0 ? $row->delivered_qty : ($row->batch_size ?? 0)),
                'load_rate'           => (float)($row->load_rate ?? 0),
                'amount_untaxed'      => (float)($row->load_untax_amount ?? 0),
                'amount_tax'          => (float)($row->load_tax_amount ?? 0),
                'amount_total'        => (float)($row->load_total_amount ?? 0),
                'invoice_number'      => $row->invoice_number ?: '-',
                'credit_note_number'  => !empty($row->cn_number) ? (($row->cn_prefix ?? '') . $row->cn_number) : '-',
            ];
        });

        return [
            'transactions'               => $records->values()->all(),
            'items'                      => $records->values()->all(),
            'total_cancelled_dispatches' => $records->count(),
            'total_quantity'             => (float)$records->sum('quantity'),
            'total_untaxed'              => (float)$records->sum('amount_untaxed'),
            'total_tax'                  => (float)$records->sum('amount_tax'),
            'total_amount'               => (float)$records->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        return 'Cancelled Dispatch Report';
    }
}
