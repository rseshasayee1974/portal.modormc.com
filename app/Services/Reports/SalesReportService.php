<?php

namespace App\Services\Reports;

use App\Models\Patron;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $patronId = $params['patron_id'] ?? null;
        $truckId  = $params['truck_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];

        // Query sales dispatches linked to batches, invoices, personnel, machines, taxes, and e-invoices
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
            ->leftJoin('mm_product_units as u', 'u.id', '=', DB::raw('COALESCE(d.uom_id, dm.unit_id, m.unit_id)'))
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_machines as pump_m', 'pump_m.id', '=', 'd.concrete_pump')
            ->leftJoin('mm_personnels as drv', 'drv.id', '=', 'd.driver_id')
            ->leftJoin('mm_personnels as op', 'op.id', '=', DB::raw('COALESCE(d.operator_id, b.operator_id)'))
            ->leftJoin('mm_personnels as se', 'se.id', '=', 'd.sales_executive_id')
            ->leftJoin('mm_users as u_creator', 'u_creator.id', '=', 'd.created_by')
            ->leftJoin('mm_taxes as tx', 'tx.id', '=', 'd.load_tax_id')
            ->leftJoin('mm_dispatch_statuses as ds', function ($join) {
                $join->on('ds.dispatch_id', '=', 'd.id')
                     ->whereNull('ds.deleted_at');
            })
            ->leftJoin('mm_invoices as inv', function ($join) {
                $join->on('inv.id', '=', 'ds.invoice_id')
                     ->whereNull('inv.deleted_at');
            })
            ->leftJoin('mm_einvoice_invoice_rel as einv_rel', 'einv_rel.invoice_id', '=', 'inv.id')
            ->where('d.plant_id', $plantId)
            ->whereNull('d.deleted_at')
            ->where(function ($q) {
                $q->whereNull('d.dispatch_status')
                  ->orWhere('d.dispatch_status', '!=', 'Cancelled');
            });

        // Date filter matching dispatch_time or invoice_date or fallback to created_at
        $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('d.dispatch_time', [$start, $end])
              ->orWhereBetween('inv.invoice_date', [$start, $end])
              ->orWhere(function ($sq) use ($start, $end) {
                  $sq->whereNull('d.dispatch_time')
                     ->whereBetween('d.created_at', [$start, $end]);
              });
        });

        // Filter by customer/party if requested
        if ($patronId) {
            $query->where(function ($q) use ($patronId) {
                $q->where('d.customer_id', $patronId)
                  ->orWhere('so.customer_id', $patronId);
            });
        }

        // Filter by vehicle/truck if requested
        if ($truckId) {
            $query->where('d.truck_id', $truckId);
        }

        $dispatches = $query->select([
            'd.id as dispatch_id',
            'd.prefix as dispatch_prefix',
            'd.dispatch_no',
            'd.dispatch_reference',
            'd.dispatch_time',
            'd.delivery_time',
            'd.delivered_qty',
            'd.load_rate',
            'd.load_untax_amount',
            'd.load_tax_amount',
            'd.load_total_amount',
            'd.pass_amount',
            'd.discount_amount',
            'd.adjustment_amount',
            'd.transport_expenses',
            'd.round_off',
            'd.payment_mode',
            'd.concrete_pump',
            'd.pump_charges',
            'd.empty_weight_truck',
            'd.loaded_weight_truck',
            'd.net_weight',
            'd.customer_id',
            'd.unload_site_id',
            'd.mixdesign_id',
            'd.created_at as dispatch_created_at',
            'd.updated_at as dispatch_updated_at',
            'b.id as batch_id',
            'b.batch_no',
            'b.batch_size',
            'b.shift',
            'b.start_time as batch_start_time',
            DB::raw('COALESCE(dp.legal_name, p.legal_name) as customer_name'),
            DB::raw('COALESCE(ds_site.name, s.name) as site_name'),
            DB::raw('COALESCE(dm.design_name, m.design_name) as mix_name'),
            DB::raw('COALESCE(cg.name, dm.design_type, m.design_type, "N/A") as concrete_grade'),
            DB::raw('COALESCE(u.unit_code, u.unit_name, "m³") as uom'),
            't.id as truck_id',
            't.registration as truck_no',
            DB::raw('COALESCE(pump_m.registration, pump_m.vehicle_model, d.concrete_pump) as pump_name'),
            DB::raw('COALESCE(drv.full_name, TRIM(CONCAT(COALESCE(drv.first_name, ""), " ", COALESCE(drv.last_name, "")))) as driver_name'),
            DB::raw('COALESCE(op.full_name, TRIM(CONCAT(COALESCE(op.first_name, ""), " ", COALESCE(op.last_name, "")))) as operator_name'),
            DB::raw('COALESCE(se.full_name, TRIM(CONCAT(COALESCE(se.first_name, ""), " ", COALESCE(se.last_name, "")))) as sales_executive_name'),
            DB::raw('COALESCE(u_creator.username, u_creator.email) as created_by_name'),
            'tx.tax_name',
            'tx.tax_rate',
            'ds.invoice_id',
            'ds.invoice_date as status_invoice_date',
            'ds.invoice_number as status_invoice_number',
            'ds.is_tax_inclusive',
            'ds.receiver_name',
            'ds.receive_mobile',
            'inv.prefix as invoice_prefix',
            'inv.invoice_number',
            'inv.invoice_date',
            'inv.eway_bill_no',
            'inv.eway_bill_date',
            'inv.einvoice_status as inv_einvoice_status',
            'inv.einvoice_irn as inv_einvoice_irn',
            'inv.einvoice_ack_no as inv_einvoice_ack_no',
            'inv.einvoice_ack_date as inv_einvoice_ack_date',
            'einv_rel.einv_ackno',
            'einv_rel.einv_ack_date',
            'einv_rel.einv_irn',
            'einv_rel.einv_status',
        ])
        ->orderByRaw('COALESCE(d.dispatch_time, d.created_at) ASC')
        ->orderBy('d.id', 'ASC')
        ->get();

        // 1. Transaction-level rows (Comprehensive Detailed Dispatch Breakdown)
        $transactions = $dispatches->map(function ($row) {
            $invNum = !empty($row->invoice_number)
                ? (($row->invoice_prefix ?? '') . $row->invoice_number)
                : (!empty($row->status_invoice_number) ? $row->status_invoice_number : '');

            $invDate = $row->invoice_date ?? $row->status_invoice_date;
            $formattedInvDate = $invDate ? Carbon::parse($invDate)->format('d-M-Y') : null;

            $dispatchDate = $row->dispatch_time 
                ? Carbon::parse($row->dispatch_time) 
                : ($row->dispatch_updated_at ? Carbon::parse($row->dispatch_updated_at) : now());

            $createdAt = $row->dispatch_created_at ? Carbon::parse($row->dispatch_created_at)->format('d-M-Y H:i') : '';

            $dispatchNo = ($row->dispatch_prefix ?? '') . ($row->dispatch_no ?? $row->dispatch_id);
            $batchNo = $row->batch_no ? ('B' . $row->batch_no) : 'N/A';
            $customerName = $row->customer_name ?? 'N/A';

            $totalAmount   = (float)$row->load_total_amount;
            $untaxedAmount = (float)$row->load_untax_amount;
            $taxAmount     = (float)$row->load_tax_amount;

            // Resolve E-invoice references
            $einvAckNo   = $row->einv_ackno ?: ($row->inv_einvoice_ack_no ?: null);
            $einvAckDate = $row->einv_ack_date ?: ($row->inv_einvoice_ack_date ?: null);
            $einvIrn     = $row->einv_irn ?: ($row->inv_einvoice_irn ?: null);
            $einvStatus  = $row->einv_status ?: ($row->inv_einvoice_status ?: null);

            // Resolve E-way bill references
            $ewayBillNo   = $row->eway_bill_no ?: null;
            $ewayBillDate = $row->eway_bill_date ? Carbon::parse($row->eway_bill_date)->format('d-M-Y') : null;
            $ewayStatus   = !empty($ewayBillNo) ? 'Generated' : 'Pending';

            return [
                'id'                   => $row->dispatch_id,
                'dispatch_timestamp'   => $dispatchDate->timestamp,
                'date'                 => $dispatchDate->format('d-M-Y'),
                'time'                 => $dispatchDate->format('h:i A'),
                'datetime'             => $dispatchDate->format('d-M-Y h:i A'),
                'dispatch_date'        => $dispatchDate->format('d-M-Y'),
                'dispatch_time'        => $dispatchDate->format('H:i:s'),
                'dispatch_no'          => $dispatchNo,
                'dispatch_reference'   => $row->dispatch_reference ?: '-',
                'voucher_type'         => 'SALES',
                'voucher_no'           => $invNum ?: $dispatchNo,
                
                // Invoiced details
                'invoice_number'       => $invNum ?: '-',
                'invoice_date'         => $formattedInvDate ?: '-',
                'invoiced_date'        => $formattedInvDate ?: '-',
                
                // Customer & Unloading site
                'customer_name'        => $customerName,
                'party_name'           => $customerName,
                'unloading_site'       => $row->site_name ?? 'N/A',
                'site_name'            => $row->site_name ?? 'N/A',
                
                // Product / Mix design / Grade
                'mix_name'             => $row->mix_name ?? 'N/A',
                'mix_design_name'      => $row->mix_name ?? 'N/A',
                'concrete_grade'       => $row->concrete_grade ?? 'N/A',
                'concrete_grade_type'  => $row->concrete_grade ?? 'N/A',
                'uom'                  => $row->uom ?? 'm³',
                
                // Batch details
                'batch_id'             => $row->batch_id,
                'batch_no'             => $batchNo,
                'batch_number'         => $batchNo,
                'batch_size'           => (float)($row->batch_size ?? 0),
                'shift'                => $row->shift ?: 'Regular',
                
                // Truck & Weights
                'truck_id'             => $row->truck_id,
                'truck_no'             => $row->truck_no ?? '-',
                'empty_weight'         => (float)($row->empty_weight_truck ?? 0),
                'truck_empty'          => (float)($row->empty_weight_truck ?? 0),
                'loaded_weight'        => (float)($row->loaded_weight_truck ?? 0),
                'truck_loaded'         => (float)($row->loaded_weight_truck ?? 0),
                'net_weight'           => (float)($row->net_weight ?? 0),
                'netweight'            => (float)($row->net_weight ?? 0),
                
                // Quantities & Rates
                'quantity'             => (float)$row->delivered_qty,
                'delivered_qty'        => (float)$row->delivered_qty,
                'rate'                 => (float)$row->load_rate,
                'load_rate'            => (float)$row->load_rate,
                
                // Concrete Pump & Charges
                'concrete_pump'        => $row->pump_name ?: ($row->concrete_pump ?: '-'),
                'pump_type'            => $row->pump_name ?: ($row->concrete_pump ?: '-'),
                'pump_rate'            => (float)($row->pump_charges ?? 0),
                'pump_charges'         => (float)($row->pump_charges ?? 0),
                'hire_charge'          => (float)($row->transport_expenses ?? 0),
                
                // Amounts, Discounts, Pass Amount & Adjustments
                'pass_amount'          => (float)($row->pass_amount ?? 0),
                'discount_amount'      => (float)($row->discount_amount ?? 0),
                'adjustment'           => (float)($row->adjustment_amount ?? 0),
                'adjustment_amount'    => (float)($row->adjustment_amount ?? 0),
                'round_off'            => (float)($row->round_off ?? 0),
                'amount_untaxed'       => $untaxedAmount,
                'amount_tax'           => $taxAmount,
                'amount_total'         => $totalAmount,
                'amounts'              => $totalAmount,
                'amount'               => $totalAmount,
                
                // Taxes
                'tax_name'             => $row->tax_name ? ($row->tax_name . ($row->tax_rate ? " ({$row->tax_rate}%)" : '')) : '-',
                'tax_rate'             => (float)($row->tax_rate ?? 0),
                'tax_amount'           => $taxAmount,
                'is_tax_inclusive'     => !empty($row->is_tax_inclusive) ? 'Yes' : 'No',
                
                // Personnel (Driver, Operator, Sales Executive, Created By)
                'driver_name'          => $row->driver_name ?: '-',
                'operator_name'        => $row->operator_name ?: '-',
                'sales_executive_name' => $row->sales_executive_name ?: '-',
                'created_by'           => $row->created_by_name ?: '-',
                'created_at'           => $createdAt,
                
                // Payment & Receiver details
                'payment_mode'         => $row->payment_mode ?: '-',
                'receiver_name'        => $row->receiver_name ?: '-',
                'receiver_mobile'      => $row->receive_mobile ?: '-',
                
                // E-way bill details
                'eway_bill_status'     => $ewayStatus,
                'eway_bill_number'     => $ewayBillNo ?: '-',
                'eway_bill_date'       => $ewayBillDate ?: '-',
                
                // E-invoice details
                'einvoice_status'      => $einvStatus ?: '-',
                'einvoice_number'      => $einvAckNo ?: '-',
                'einv_ackno'           => $einvAckNo,
                'einv_ack_date'        => $einvAckDate ? Carbon::parse($einvAckDate)->format('d-M-Y H:i') : '-',
                'einvoice_irn'         => $einvIrn ?: '-',
                'einv_irn'             => $einvIrn,
                'einv_status'          => $einvStatus,
                
                'narration'            => '[' . $customerName . '] ' . $dispatchNo . ($invNum ? " (Inv: {$invNum})" : ''),
                'type'                 => 'Cr',
                'debit'                => 0,
                'credit'               => $totalAmount,
            ];
        });

        // 2. Product Consolidated Report (Mix Design & Concrete Grade wise)
        $productSummary = $dispatches->groupBy(function ($d) {
            return ($d->mixdesign_id ? 'md_' . $d->mixdesign_id : '') . '_' . ($d->mix_name ?: '') . '_' . ($d->concrete_grade ?: '');
        })->map(function ($items) {
            $first         = $items->first();
            $totalQty      = (float)$items->sum('delivered_qty');
            $untaxed       = (float)$items->sum('load_untax_amount');
            $tax           = (float)$items->sum('load_tax_amount');
            $total         = (float)$items->sum('load_total_amount');
            $batchSize     = (float)$items->sum('batch_size');
            $truckEmpty    = (float)$items->sum('empty_weight_truck');
            $loadedWeight  = (float)$items->sum('loaded_weight_truck');
            $netWeight     = (float)$items->sum('net_weight');

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

        // 3. Customer Consolidated Report (Party wise)
        $customerSummary = $dispatches->groupBy(function ($d) {
            return $d->customer_id ?: ($d->customer_name ?: 'Unknown Customer');
        })->map(function ($items) {
            $first         = $items->first();
            $partyName     = $first->customer_name ?: 'Unknown Customer';
            $totalQty      = (float)$items->sum('delivered_qty');
            $untaxed       = (float)$items->sum('load_untax_amount');
            $tax           = (float)$items->sum('load_tax_amount');
            $total         = (float)$items->sum('load_total_amount');
            $batchSize     = (float)$items->sum('batch_size');
            $truckEmpty    = (float)$items->sum('empty_weight_truck');
            $loadedWeight  = (float)$items->sum('loaded_weight_truck');
            $netWeight     = (float)$items->sum('net_weight');

            return [
                'party_name'     => $partyName,
                'customer_name'  => $partyName,
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
        })->values()->sortBy('party_name')->values();

        // 4. Truck Consolidated Report (Vehicle wise)
        $truckSummary = $dispatches->groupBy(function ($d) {
            return $d->truck_id ?: ($d->truck_no ?: 'Unknown Vehicle');
        })->map(function ($items) {
            $first         = $items->first();
            $truckNo       = $first->truck_no ?: 'Unknown Vehicle';
            $totalQty      = (float)$items->sum('delivered_qty');
            $untaxed       = (float)$items->sum('load_untax_amount');
            $tax           = (float)$items->sum('load_tax_amount');
            $total         = (float)$items->sum('load_total_amount');
            $batchSize     = (float)$items->sum('batch_size');
            $truckEmpty    = (float)$items->sum('empty_weight_truck');
            $loadedWeight  = (float)$items->sum('loaded_weight_truck');
            $netWeight     = (float)$items->sum('net_weight');

            return [
                'truck_no'       => $truckNo,
                'vehicle_no'     => $truckNo,
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
        })->values()->sortBy('truck_no')->values();

        // 5. Unload Site Consolidated Report (Site wise)
        $siteSummary = $dispatches->groupBy(function ($d) {
            return $d->unload_site_id ?: ($d->site_name ?: 'Unknown Site');
        })->map(function ($items) {
            $first         = $items->first();
            $siteName      = $first->site_name ?: 'Unknown Site';
            $totalQty      = (float)$items->sum('delivered_qty');
            $untaxed       = (float)$items->sum('load_untax_amount');
            $tax           = (float)$items->sum('load_tax_amount');
            $total         = (float)$items->sum('load_total_amount');
            $batchSize     = (float)$items->sum('batch_size');
            $truckEmpty    = (float)$items->sum('empty_weight_truck');
            $loadedWeight  = (float)$items->sum('loaded_weight_truck');
            $netWeight     = (float)$items->sum('net_weight');

            return [
                'site_id'        => $first->unload_site_id ?? null,
                'site_name'      => $siteName,
                'customer_name'  => $first->customer_name ?: '-',
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

        // 6. Payment Mode Consolidated Report (Payment mode wise)
        $paymentModeSummary = $dispatches->groupBy(function ($d) {
            return !empty($d->payment_mode) ? strtolower(trim($d->payment_mode)) : 'unspecified';
        })->map(function ($items) {
            $first         = $items->first();
            $modeLabel     = !empty($first->payment_mode) ? ucfirst($first->payment_mode) : 'Not Specified';
            $totalQty      = (float)$items->sum('delivered_qty');
            $untaxed       = (float)$items->sum('load_untax_amount');
            $tax           = (float)$items->sum('load_tax_amount');
            $total         = (float)$items->sum('load_total_amount');
            $batchSize     = (float)$items->sum('batch_size');
            $truckEmpty    = (float)$items->sum('empty_weight_truck');
            $loadedWeight  = (float)$items->sum('loaded_weight_truck');
            $netWeight     = (float)$items->sum('net_weight');

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

        $totalUntaxed = (float)$dispatches->sum('load_untax_amount');
        $totalTax     = (float)$dispatches->sum('load_tax_amount');
        $totalAmount  = (float)$dispatches->sum('load_total_amount');

        return [
            'opening_balance'            => 0,
            'transactions'               => $transactions,
            'total_untaxed'              => $totalUntaxed,
            'total_tax'                  => $totalTax,
            'total_amount'               => $totalAmount,

            // Product Consolidated Report (Mix Design & Concrete Grade wise)
            'product_summary'            => $productSummary,
            'mix_design_summary'         => $productSummary,
            'total_quantity'             => (float)$productSummary->sum('quantity'),
            'total_product_batch_size'   => (float)$productSummary->sum('batch_size'),
            'total_product_truck_empty'  => (float)$productSummary->sum('truck_empty'),
            'total_product_loaded_weight'=> (float)$productSummary->sum('loaded_weight'),
            'total_product_net_weight'   => (float)$productSummary->sum('netweight'),
            'total_product_untaxed'      => (float)$productSummary->sum('amount_untaxed'),
            'total_product_tax'          => (float)$productSummary->sum('amount_tax'),
            'total_product_amount'       => (float)$productSummary->sum('amount_total'),

            // Retained for legacy template mappings
            'total_dispatch_quantity'    => (float)$productSummary->sum('quantity'),
            'total_dispatch_untaxed'     => (float)$productSummary->sum('amount_untaxed'),
            'total_dispatch_tax'         => (float)$productSummary->sum('amount_tax'),
            'total_dispatch_amount'      => (float)$productSummary->sum('amount_total'),

            // Customer Consolidated Report
            'party_summary'              => $customerSummary,
            'customer_summary'           => $customerSummary,
            'total_party_batch_size'     => (float)$customerSummary->sum('batch_size'),
            'total_party_quantity'       => (float)$customerSummary->sum('quantity'),
            'total_party_truck_empty'    => (float)$customerSummary->sum('truck_empty'),
            'total_party_loaded_weight'  => (float)$customerSummary->sum('loaded_weight'),
            'total_party_net_weight'     => (float)$customerSummary->sum('netweight'),
            'total_party_untaxed'        => (float)$customerSummary->sum('amount_untaxed'),
            'total_party_tax'            => (float)$customerSummary->sum('amount_tax'),
            'total_party_amount'         => (float)$customerSummary->sum('amount_total'),

            // Truck Consolidated Report
            'truck_summary'              => $truckSummary,
            'total_truck_trips'          => (int)$truckSummary->sum('trips_count'),
            'total_truck_batch_size'     => (float)$truckSummary->sum('batch_size'),
            'total_truck_quantity'       => (float)$truckSummary->sum('quantity'),
            'total_truck_empty'          => (float)$truckSummary->sum('truck_empty'),
            'total_truck_loaded_weight'  => (float)$truckSummary->sum('loaded_weight'),
            'total_truck_net_weight'     => (float)$truckSummary->sum('netweight'),
            'total_truck_untaxed'        => (float)$truckSummary->sum('amount_untaxed'),
            'total_truck_tax'            => (float)$truckSummary->sum('amount_tax'),
            'total_truck_amount'         => (float)$truckSummary->sum('amount_total'),

            // Unload Site Consolidated Report
            'site_summary'               => $siteSummary,
            'total_site_trips'           => (int)$siteSummary->sum('trips_count'),
            'total_site_batch_size'      => (float)$siteSummary->sum('batch_size'),
            'total_site_quantity'        => (float)$siteSummary->sum('quantity'),
            'total_site_truck_empty'     => (float)$siteSummary->sum('truck_empty'),
            'total_site_loaded_weight'   => (float)$siteSummary->sum('loaded_weight'),
            'total_site_net_weight'      => (float)$siteSummary->sum('netweight'),
            'total_site_untaxed'         => (float)$siteSummary->sum('amount_untaxed'),
            'total_site_tax'             => (float)$siteSummary->sum('amount_tax'),
            'total_site_amount'          => (float)$siteSummary->sum('amount_total'),

            // Payment Mode Consolidated Report
            'payment_mode_summary'       => $paymentModeSummary,
            'total_payment_mode_trips'   => (int)$paymentModeSummary->sum('trips_count'),
            'total_payment_mode_batch_size' => (float)$paymentModeSummary->sum('batch_size'),
            'total_payment_mode_quantity'=> (float)$paymentModeSummary->sum('quantity'),
            'total_payment_mode_truck_empty' => (float)$paymentModeSummary->sum('truck_empty'),
            'total_payment_mode_loaded_weight' => (float)$paymentModeSummary->sum('loaded_weight'),
            'total_payment_mode_net_weight' => (float)$paymentModeSummary->sum('netweight'),
            'total_payment_mode_untaxed' => (float)$paymentModeSummary->sum('amount_untaxed'),
            'total_payment_mode_tax'     => (float)$paymentModeSummary->sum('amount_tax'),
            'total_payment_mode_amount'  => (float)$paymentModeSummary->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id'])
            ? (Patron::whereNull('deleted_at')->find($params['patron_id'])?->legal_name ?? 'Customer')
            : 'All Customer Sales';
    }
}