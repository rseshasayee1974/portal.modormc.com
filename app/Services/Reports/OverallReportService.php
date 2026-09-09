<?php

namespace App\Services\Reports;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plant;
use App\Models\PumpBoomDeploymentSchedule;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OverallReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $params['plant_id'] ?? $this->ctx->plantId() ?? session('active_plant_id');

        // Parse date boundaries (defaults to today)
        $start = !empty($params['start']) 
            ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($params['start'])) ? Carbon::parse($params['start'])->startOfDay()->toDateTimeString() : Carbon::parse($params['start'])->toDateTimeString())
            : now()->startOfDay()->toDateTimeString();

        $end = !empty($params['end']) 
            ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($params['end'])) ? Carbon::parse($params['end'])->endOfDay()->toDateTimeString() : Carbon::parse($params['end'])->toDateTimeString())
            : now()->endOfDay()->toDateTimeString();

        $startDateOnly = substr($start, 0, 10);
        $endDateOnly   = substr($end, 0, 10);

        // -------------------------------------------------------------
        // 1. PRODUCTION & BATCHING
        // -------------------------------------------------------------
        $batchList = [];
        $gradeWiseBatches = [];
        $totalBatchesCount = 0;
        $totalBatchesVolume = 0.0;

        try {
            $batchQuery = Batch::query()
                ->with(['salesOrder.mixDesign:id,design_name,design_type'])
                ->whereNull('deleted_at');
            if ($plantId) $batchQuery->where('plant_id', $plantId);
            $batchQuery->whereBetween('created_at', [$start, $end]);

            $batches = $batchQuery->orderBy('id', 'desc')->get();
            $totalBatchesCount = $batches->count();
            $totalBatchesVolume = round((float)$batches->sum('batch_size'), 3);

            foreach ($batches as $b) {
                $grade = $b->salesOrder?->mixDesign?->design_name ?? $b->salesOrder?->mixDesign?->design_type ?? '';
                if (!isset($gradeWiseBatches[$grade])) {
                    $gradeWiseBatches[$grade] = ['grade' => $grade, 'count' => 0, 'volume' => 0.0];
                }
                $gradeWiseBatches[$grade]['count']++;
                $gradeWiseBatches[$grade]['volume'] = round($gradeWiseBatches[$grade]['volume'] + (float)$b->batch_size, 3);
            }

            $batchList = $batches->map(function ($b, $i) {
                return [
                    'index'          => $i + 1,
                    'id'             => $b->id,
                    'batch_no'       => $b->batch_no,
                    'batch_size'     => (float)$b->batch_size,
                    'mix_design'     => $b->salesOrder?->mixDesign?->design_name ?? '',
                    'created_at'     => $b->created_at ? $b->created_at->format('d/m/Y h:i A') : '-',
                ];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Batching query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 2. DISPATCHES & LOGISTICS
        // -------------------------------------------------------------
        $dispatchList = [];
        $totalDispatchesCount = 0;
        $totalDeliveredQty = 0.0;
        $totalNetWeight = 0.0;
        $totalDispatchValue = 0.0;
        $totalDispatchPumpChg = 0.0;
        $uniqueTrucksCount = 0;
        $uniqueDriversCount = 0;

        try {
            $dispatchQuery = Dispatch::query()
                ->with([
                    'customer:id,legal_name,code',
                    'truck:id,registration',
                    'driver:id,first_name,last_name',
                    'unloadSite:id,name',
                ])
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('dispatch_status')
                      ->orWhere('dispatch_status', '!=', 'Cancelled');
                });
            if ($plantId) $dispatchQuery->where('plant_id', $plantId);
            $dispatchQuery->where(function ($q) use ($start, $end) {
                $q->whereBetween('dispatch_time', [$start, $end])
                  ->orWhere(function ($sq) use ($start, $end) {
                      $sq->whereNull('dispatch_time')
                         ->whereBetween('created_at', [$start, $end]);
                  });
            });

            $dispatches = $dispatchQuery->orderBy('id', 'desc')->get();
            $totalDispatchesCount = $dispatches->count();
            $totalDeliveredQty    = round((float)$dispatches->sum('delivered_qty'), 3);
            $totalNetWeight       = round((float)$dispatches->sum('net_weight'), 2);
            $totalDispatchValue   = round((float)$dispatches->sum('load_total_amount'), 2);
            $totalDispatchPumpChg = round((float)$dispatches->sum('pump_charges'), 2);

            $uniqueTrucksCount  = $dispatches->pluck('truck_id')->filter()->unique()->count();
            $uniqueDriversCount = $dispatches->pluck('driver_id')->filter()->unique()->count();

            $dispatchList = $dispatches->map(function ($d, $i) {
                $truckReg = $d->truck?->registration ?? '';
                $driverName = trim(($d->driver?->first_name ?? '') . ' ' . ($d->driver?->last_name ?? '')) ?: '-';
                return [
                    'index'          => $i + 1,
                    'id'             => $d->id,
                    'docket_no'      => trim(($d->prefix ?? '') . ' ' . ($d->dispatch_no ?? $d->dispatch_reference ?? ('DSP-' . $d->id))),
                    'customer_name'  => $d->customer?->legal_name ?? '',
                    'site_name'      => $d->unloadSite?->name ?? '',
                    'truck_no'       => $truckReg,
                    'driver_name'    => $driverName,
                    'delivered_qty'  => (float)$d->delivered_qty,
                    'net_weight'     => (float)$d->net_weight,
                    'pump_charges'   => (float)$d->pump_charges,
                    'total_amount'   => (float)$d->load_total_amount,
                    'dispatch_time'  => $d->dispatch_time ? Carbon::parse($d->dispatch_time)->format('d/m/Y h:i A') : ($d->created_at ? $d->created_at->format('d/m/Y h:i A') : '-'),
                ];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Dispatches query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 3. PUMP & BOOM OPERATIONS
        // -------------------------------------------------------------
        $pumpList = [];
        $totalPumpDeployments = 0;
        $totalPlannedPumpM3 = 0.0;

        try {
            if (Schema::hasTable('mm_pump_boom_deployment_schedule')) {
                $pumpQuery = PumpBoomDeploymentSchedule::query()->whereNull('deleted_at');
                if ($plantId) $pumpQuery->where('plant_id', $plantId);
                $pumpQuery->whereBetween('schedule_date', [$startDateOnly, $endDateOnly]);

                $pumpSchedules = $pumpQuery->orderBy('id', 'desc')->get();
                $totalPumpDeployments = $pumpSchedules->count();
                $totalPlannedPumpM3   = round((float)$pumpSchedules->sum('planned_qty_m3'), 3);

                $pumpList = $pumpSchedules->map(function ($p, $i) {
                    return [
                        'index'            => $i + 1,
                        'id'               => $p->id,
                        'pump_no'          => $p->pump_no ?: 'Pump Vehicle',
                        'customer_name'    => $p->site_name ?: 'Pour Site',
                        'site_name'        => $p->site_name ?: 'Site',
                        'grade'            => $p->grade ?: 'Concrete Grade',
                        'planned_qty_m3'   => (float)$p->planned_qty_m3,
                        'status'           => $p->status ?: 'Scheduled',
                        'operator_name'    => $p->operator_name ?: '-',
                        'boom_length_m'    => (float)($p->boom_length_m ?? 0),
                    ];
                })->all();
            }
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Pump schedules query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 4. SALES PIPELINE: QUOTATIONS & SALES ORDERS
        // -------------------------------------------------------------
        $quotationList = [];
        $quotationsCount = 0;
        $quotationsTotal = 0.0;

        try {
            $quoteQuery = Quotation::query()->whereNull('deleted_at');
            if ($plantId) $quoteQuery->where('plant_id', $plantId);
            $quoteQuery->where(function ($q) use ($start, $end, $startDateOnly, $endDateOnly) {
                $q->whereBetween('quote_date', [$startDateOnly, $endDateOnly])
                  ->orWhereBetween('created_at', [$start, $end]);
            });
            $quotations = $quoteQuery->with('patron:id,legal_name')->orderBy('id', 'desc')->get();
            $quotationsCount = $quotations->count();
            $quotationsTotal = round((float)$quotations->sum('amount_total'), 2);

            $quotationList = $quotations->map(fn($q, $i) => [
                'index'        => $i + 1,
                'id'           => $q->id,
                'quote_no'     => $q->reference ?? ('QUO-' . $q->id),
                'customer'     => $q->patron?->legal_name ?? 'Customer',
                'date'         => $q->quote_date ? Carbon::parse($q->quote_date)->format('d/m/Y') : '-',
                'total_amount' => (float)$q->amount_total,
                'status'       => $q->status == 2 ? 'Accepted' : ($q->status == 1 ? 'Sent' : ($q->status == 3 ? 'Rejected' : 'Draft')),
            ])->all();
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Quotation query error - ' . $e->getMessage());
        }

        $salesOrderList = [];
        $salesOrdersCount = 0;
        $salesOrdersTotal = 0.0;

        try {
            $soQuery = SalesOrder::query()->whereNull('deleted_at');
            if ($plantId) $soQuery->where('plant_id', $plantId);
            $soQuery->where(function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                  ->orWhere(function ($sq) use ($start, $end) {
                      $sq->whereNotNull('scheduled_start')
                         ->whereBetween('scheduled_start', [$start, $end]);
                  });
            });
            $salesOrders = $soQuery->with('customer:id,legal_name')->orderBy('id', 'desc')->get();
            $salesOrdersCount = $salesOrders->count();

            $salesOrderList = $salesOrders->map(function ($so, $i) use (&$salesOrdersTotal) {
                $orderTotal = round((float)(($so->total_qty ?? 0) * ($so->rate ?? 0)), 2);
                $salesOrdersTotal += $orderTotal;
                return [
                    'index'        => $i + 1,
                    'id'           => $so->id,
                    'order_no'     => trim(($so->prefix ?? '') . ' ' . ($so->order_no ?? ('SO-' . $so->id))),
                    'customer'     => $so->customer?->legal_name ?? 'Customer',
                    'date'         => $so->created_at ? $so->created_at->format('d/m/Y') : '-',
                    'pump_required'=> (bool)($so->concrete_pump ?? false),
                    'pump_rate'    => (float)($so->pump_rate ?? 0),
                    'total_amount' => $orderTotal,
                    'status'       => $so->status == 3 ? 'Completed' : ($so->status == 1 ? 'Confirmed' : 'Draft'),
                ];
            })->all();
            $salesOrdersTotal = round($salesOrdersTotal, 2);
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Sales order query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 5. INVOICING, BILLING & CHARGES
        // -------------------------------------------------------------
        $invoiceList = [];
        $salesInvoicesCount = 0;
        $salesTotalBilled = 0.0;
        $salesSubtotal = 0.0;
        $salesOutputTax = 0.0;
        $salesShippingChg = 0.0;
        $salesPaidAmt = 0.0;
        $salesBalanceDue = 0.0;
        $creditSalesCount = 0;
        $salesCgst = 0.0;
        $salesSgst = 0.0;
        $salesIgst = 0.0;
        $eInvoicesGeneratedCount = 0;
        $eWaybillsGeneratedCount = 0;

        try {
            $salesInvQuery = Invoice::query()
                ->with([
                    'partner:id,legal_name,code,gstin',
                    'orderTaxes',
                    'einvoiceRelation',
                    'ewaybillDetail',
                ])
                ->where('invoice_type', 'sales')
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'Cancelled');
                });
            if ($plantId) $salesInvQuery->where('plant_id', $plantId);
            $salesInvQuery->whereBetween('invoice_date', [$startDateOnly, $endDateOnly]);

            $salesInvoices = $salesInvQuery->orderBy('id', 'desc')->get();
            $salesInvoicesCount = $salesInvoices->count();
            $salesTotalBilled   = round((float)$salesInvoices->sum('total_amount'), 2);
            $salesSubtotal      = round((float)$salesInvoices->sum('subtotal'), 2);
            $salesOutputTax     = round((float)$salesInvoices->sum('tax_amount'), 2);
            $salesShippingChg   = round((float)$salesInvoices->sum('shipping_charges'), 2);
            $salesPaidAmt       = round((float)$salesInvoices->sum('paid_amount'), 2);
            $salesBalanceDue    = round((float)$salesInvoices->sum('balance_amount'), 2);
            $creditSalesCount   = $salesInvoices->where('balance_amount', '>', 0)->count();

            foreach ($salesInvoices as $si) {
                if ($si->relationLoaded('orderTaxes')) {
                    foreach ($si->orderTaxes as $ot) {
                        $tName = strtoupper($ot->name ?? '');
                        $tAmt = (float)$ot->amount;
                        if (str_contains($tName, 'CGST')) $salesCgst += $tAmt;
                        elseif (str_contains($tName, 'SGST') || str_contains($tName, 'UTGST')) $salesSgst += $tAmt;
                        elseif (str_contains($tName, 'IGST')) $salesIgst += $tAmt;
                    }
                }
            }
            $salesCgst = round($salesCgst, 2);
            $salesSgst = round($salesSgst, 2);
            $salesIgst = round($salesIgst, 2);

            $eInvoicesGeneratedCount = $salesInvoices->filter(fn($si) => !empty($si->einvoice_irn) || !empty($si->einvoiceRelation?->einv_irn))->count();
            $eWaybillsGeneratedCount = $salesInvoices->filter(fn($si) => !empty($si->eway_bill_no) || !empty($si->ewaybillDetail?->ewaybill_no))->count();

            $invoiceList = $salesInvoices->map(fn($inv, $i) => [
                'index'           => $i + 1,
                'id'              => $inv->id,
                'invoice_no'      => $inv->full_number ?: ('INV-' . $inv->id),
                'customer_name'   => $inv->partner?->legal_name ?? 'Customer',
                'gstin'           => $inv->partner?->gstin ?? '-',
                'invoice_date'    => $inv->invoice_date ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-',
                'subtotal'        => (float)$inv->subtotal,
                'tax_amount'      => (float)$inv->tax_amount,
                'shipping_charges'=> (float)($inv->shipping_charges ?? 0),
                'total_amount'    => (float)$inv->total_amount,
                'paid_amount'     => (float)$inv->paid_amount,
                'balance_amount'  => (float)$inv->balance_amount,
                'einvoice_irn'    => $inv->einvoice_irn ?: ($inv->einvoiceRelation?->einv_irn ? 'Generated' : 'Pending'),
                'eway_bill_no'    => $inv->eway_bill_no ?: ($inv->ewaybillDetail?->ewaybill_no ? 'Generated' : '-'),
                'status'          => $inv->balance_amount <= 0 ? 'Paid' : ($inv->paid_amount > 0 ? 'Partially Paid' : 'Credit / Unpaid'),
            ])->all();
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Sales invoices query error - ' . $e->getMessage());
        }

        // Purchase Bills
        $purchaseBillList = [];
        $purchaseBillsCount = 0;
        $purchaseTotalBilled = 0.0;
        $purchaseInputTax = 0.0;
        $purchaseCgst = 0.0;
        $purchaseSgst = 0.0;
        $purchaseIgst = 0.0;

        try {
            $purchaseBillQuery = Invoice::query()
                ->with(['partner:id,legal_name,gstin', 'orderTaxes'])
                ->whereIn('invoice_type', ['bill', 'purchase'])
                ->whereNull('deleted_at');
            if ($plantId) $purchaseBillQuery->where('plant_id', $plantId);
            $purchaseBillQuery->whereBetween('invoice_date', [$startDateOnly, $endDateOnly]);

            $purchaseBills = $purchaseBillQuery->orderBy('id', 'desc')->get();
            $purchaseBillsCount = $purchaseBills->count();
            $purchaseTotalBilled= round((float)$purchaseBills->sum('total_amount'), 2);
            $purchaseInputTax   = round((float)$purchaseBills->sum('tax_amount'), 2);

            foreach ($purchaseBills as $pb) {
                if ($pb->relationLoaded('orderTaxes')) {
                    foreach ($pb->orderTaxes as $pot) {
                        $ptName = strtoupper($pot->name ?? '');
                        $ptAmt = (float)$pot->amount;
                        if (str_contains($ptName, 'CGST')) $purchaseCgst += $ptAmt;
                        elseif (str_contains($ptName, 'SGST') || str_contains($ptName, 'UTGST')) $purchaseSgst += $ptAmt;
                        elseif (str_contains($ptName, 'IGST')) $purchaseIgst += $ptAmt;
                    }
                }
            }
            $purchaseCgst = round($purchaseCgst, 2);
            $purchaseSgst = round($purchaseSgst, 2);
            $purchaseIgst = round($purchaseIgst, 2);

            $purchaseBillList = $purchaseBills->map(fn($b, $i) => [
                'index'        => $i + 1,
                'id'           => $b->id,
                'bill_no'      => $b->full_number ?: ('BILL-' . $b->id),
                'vendor_name'  => $b->partner?->legal_name ?? 'Supplier',
                'date'         => $b->invoice_date ? Carbon::parse($b->invoice_date)->format('d/m/Y') : '-',
                'tax_amount'   => (float)$b->tax_amount,
                'total_amount' => (float)$b->total_amount,
                'status'       => $b->balance_amount <= 0 ? 'Paid' : 'Unpaid',
            ])->all();
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Purchase bills query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 6. PROCUREMENT & GOODS INWARD
        // -------------------------------------------------------------
        $inwardList = [];
        $inwardReceiptsCount = 0;
        $totalInwardNetWeight = 0.0;

        try {
            if (Schema::hasTable('mm_purchase_order_history')) {
                $inwardQuery = PurchaseOrderHistory::query()->whereNull('deleted_at');
                if ($plantId) $inwardQuery->where('plant_id', $plantId);
                $inwardQuery->where(function ($q) use ($start, $end, $startDateOnly, $endDateOnly) {
                    $q->whereBetween('received_date', [$startDateOnly, $endDateOnly])
                      ->orWhereBetween('created_at', [$start, $end]);
                });
                $inwards = $inwardQuery->with(['order.vendor:id,legal_name', 'product:id,name', 'truck:id,registration'])->orderBy('id', 'desc')->get();
                $inwardReceiptsCount = $inwards->count();
                $totalInwardNetWeight = round((float)$inwards->sum('received_qty'), 2);

                $inwardList = $inwards->map(fn($inw, $i) => [
                    'index'       => $i + 1,
                    'id'          => $inw->id,
                    'truck_no'    => $inw->truck?->registration ?? ($inw->truck_id ? ('Truck #' . $inw->truck_id) : 'Inward Truck'),
                    'vendor'      => $inw->order?->vendor?->legal_name ?? 'Supplier',
                    'material'    => $inw->product?->name ?? 'Raw Material',
                    'net_weight'  => (float)($inw->received_qty ?? 0),
                    'date'        => $inw->received_date ? Carbon::parse($inw->received_date)->format('d/m/Y') : '-',
                ])->all();
            }
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Procurement inward query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 7. CASH, BANK COLLECTIONS & PAYMENTS (DAY BOOK)
        // -------------------------------------------------------------
        $receiptList = [];
        $paymentList = [];
        $totalReceiptsAmount = 0.0;
        $totalPaymentsAmount = 0.0;
        $netDailyCashFlow    = 0.0;
        $cashReceiptsAmount  = 0.0;
        $bankReceiptsAmount  = 0.0;
        $cashPaymentsAmount  = 0.0;
        $bankPaymentsAmount  = 0.0;

        try {
            $paymentQuery = Payment::query()
                ->with(['ledger:id,title', 'patron:id,legal_name'])
                ->whereNull('deleted_at');
            if ($plantId) $paymentQuery->where('plant_id', $plantId);
            $paymentQuery->whereBetween('transaction_date', [$startDateOnly, $endDateOnly]);

            $allPayments = $paymentQuery->orderBy('id', 'desc')->get();

            $receipts = $allPayments->filter(fn($p) => in_array(strtolower($p->transaction_type ?? ''), ['receipt', 'rcpt']));
            $payments = $allPayments->filter(fn($p) => in_array(strtolower($p->transaction_type ?? ''), ['payment', 'pmt']));

            $totalReceiptsAmount = round((float)$receipts->sum('amount'), 2);
            $totalPaymentsAmount = round((float)$payments->sum('amount'), 2);
            $netDailyCashFlow    = round($totalReceiptsAmount - $totalPaymentsAmount, 2);

            // Cash vs Bank split for receipts
            $cashReceiptsAmount = round((float)$receipts->filter(function ($p) {
                $mode = strtolower($p->transaction_mode ?? '');
                $ledger = strtolower($p->ledger?->title ?? '');
                return $mode === 'cash' || str_contains($ledger, 'cash');
            })->sum('amount'), 2);

            $bankReceiptsAmount = round($totalReceiptsAmount - $cashReceiptsAmount, 2);

            // Cash vs Bank split for payments
            $cashPaymentsAmount = round((float)$payments->filter(function ($p) {
                $mode = strtolower($p->transaction_mode ?? '');
                $ledger = strtolower($p->ledger?->title ?? '');
                return $mode === 'cash' || str_contains($ledger, 'cash');
            })->sum('amount'), 2);
            $bankPaymentsAmount = round($totalPaymentsAmount - $cashPaymentsAmount, 2);

            $receiptList = $receipts->map(fn($r, $i) => [
                'index'       => $i + 1,
                'id'          => $r->id,
                'voucher_no'  => $r->reference ?? ('RCPT-' . $r->id),
                'customer'    => $r->patron?->legal_name ?? 'Customer',
                'account'     => $r->ledger?->title ?? 'Cash/Bank',
                'mode'        => ucfirst($r->transaction_mode ?: 'Cash'),
                'amount'      => (float)$r->amount,
                'date'        => $r->transaction_date ? Carbon::parse($r->transaction_date)->format('d/m/Y') : '-',
            ])->values()->all();

            $paymentList = $payments->map(fn($p, $i) => [
                'index'       => $i + 1,
                'id'          => $p->id,
                'voucher_no'  => $p->reference ?? ('PMT-' . $p->id),
                'beneficiary' => $p->patron?->legal_name ?? 'Vendor / Expense',
                'account'     => $p->ledger?->title ?? 'Cash/Bank',
                'mode'        => ucfirst($p->transaction_mode ?: 'Bank'),
                'amount'      => (float)$p->amount,
                'date'        => $p->transaction_date ? Carbon::parse($p->transaction_date)->format('d/m/Y') : '-',
            ])->values()->all();
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Payments day-book query error - ' . $e->getMessage());
        }

        // -------------------------------------------------------------
        // 8. TAXATION & NET BALANCE CALCULATIONS
        // -------------------------------------------------------------
        $netTaxPayable = round($salesOutputTax - $purchaseInputTax, 2);

        $plant = null;
        try {
            $plant = $plantId ? Plant::with(['addresses.state'])->find($plantId) : null;
        } catch (\Throwable $e) {
            Log::warning('OverallReport: Plant lookup error - ' . $e->getMessage());
        }

        // Executive Scorecard Metrics
        $executiveSummary = [
            'sales_revenue'              => $salesTotalBilled,
            'sales_subtotal'             => $salesSubtotal,
            'total_dispatched_volume'    => $totalDeliveredQty,
            'total_dispatches_count'     => $totalDispatchesCount,
            'total_batches_produced'     => $totalBatchesVolume,
            'total_batches_count'        => $totalBatchesCount,
            'pump_deployments_count'     => $totalPumpDeployments,
            'planned_pump_volume'        => $totalPlannedPumpM3,
            'pump_charges_earned'        => $totalDispatchPumpChg,
            'hire_charges_billed'        => $salesShippingChg,
            'total_receipts'             => $totalReceiptsAmount,
            'cash_receipts'              => $cashReceiptsAmount,
            'bank_receipts'              => $bankReceiptsAmount,
            'total_payments'             => $totalPaymentsAmount,
            'cash_payments'              => $cashPaymentsAmount,
            'bank_payments'              => $bankPaymentsAmount,
            'net_cash_flow'              => $netDailyCashFlow,
            'credit_sales_amount'        => $salesBalanceDue,
            'paid_invoices_amount'       => $salesPaidAmt,
            'purchase_bills_amount'      => $purchaseTotalBilled,
            'output_gst'                 => $salesOutputTax,
            'input_gst'                  => $purchaseInputTax,
            'net_gst_payable'            => $netTaxPayable,
            'quotations_count'           => $quotationsCount,
            'quotations_value'           => $quotationsTotal,
            'sales_orders_count'         => $salesOrdersCount,
            'sales_orders_value'         => $salesOrdersTotal,
            'inward_receipts_count'      => $inwardReceiptsCount,
            'inward_materials_weight'    => $totalInwardNetWeight,
            'active_trucks_count'        => $uniqueTrucksCount,
            'active_drivers_count'       => $uniqueDriversCount,
            'ewaybills_count'            => $eWaybillsGeneratedCount,
            'einvoices_count'            => $eInvoicesGeneratedCount,
        ];

        return [
            'report_type'        => 'OVERALL',
            'report_name'        => 'Overall Plant Daily Operations & Financial MIS Report',
            'plant'              => $plant,
            'filters'            => [
                'start_date' => $startDateOnly,
                'end_date'   => $endDateOnly,
                'plant_id'   => $plantId,
            ],
            'executive_summary'  => $executiveSummary,
            'operations'         => [
                'dispatches'           => $dispatchList,
                'dispatches_count'     => $totalDispatchesCount,
                'total_dispatched_m3'  => $totalDeliveredQty,
                'total_net_weight_tons'=> $totalNetWeight,
                'batches'              => $batchList,
                'batches_count'        => $totalBatchesCount,
                'total_batched_m3'     => $totalBatchesVolume,
                'grade_wise_batches'   => array_values($gradeWiseBatches),
                'pump_schedules'       => $pumpList,
                'pump_schedules_count' => $totalPumpDeployments,
                'total_planned_pump_m3'=> $totalPlannedPumpM3,
            ],
            'dispatches'         => [
                'list'  => $dispatchList,
                'total' => $totalDeliveredQty,
                'count' => $totalDispatchesCount,
            ],
            'production'         => [
                'list'       => $batchList,
                'grade_wise' => array_values($gradeWiseBatches),
                'total'      => $totalBatchesVolume,
                'count'      => $totalBatchesCount,
            ],
            'pump_operations'    => [
                'list'  => $pumpList,
                'total' => $totalPlannedPumpM3,
                'count' => $totalPumpDeployments,
            ],
            'invoicing'          => [
                'list'                    => $invoiceList,
                'invoices'                => $invoiceList,
                'invoices_count'          => $salesInvoicesCount,
                'total_billed'            => $salesTotalBilled,
                'subtotal'                => $salesSubtotal,
                'total_tax'               => $salesOutputTax,
                'pump_charges'            => $totalDispatchPumpChg,
                'hire_transport_charges'  => $salesShippingChg,
                'credit_sales_amount'     => $salesBalanceDue,
                'paid_amount'             => $salesPaidAmt,
                'credit_invoices_count'   => $creditSalesCount,
                'purchase_bills'          => $purchaseBillList,
                'purchase_bills_list'     => $purchaseBillList,
                'purchase_bills_count'    => $purchaseBillsCount,
                'purchase_bills_total'    => $purchaseTotalBilled,
                'purchase_input_tax'      => $purchaseInputTax,
                'ewaybills_count'         => $eWaybillsGeneratedCount,
                'einvoices_count'         => $eInvoicesGeneratedCount,
            ],
            'taxes'              => [
                'output_cgst'        => $salesCgst,
                'output_sgst'        => $salesSgst,
                'output_igst'        => $salesIgst,
                'output_total'       => $salesOutputTax,
                'input_cgst'         => $purchaseCgst,
                'input_sgst'         => $purchaseSgst,
                'input_igst'         => $purchaseIgst,
                'input_total'        => $purchaseInputTax,
                'net_tax_payable'    => $netTaxPayable,
            ],
            'cash_flow'          => [
                'receipts'           => $receiptList,
                'receipts_list'      => $receiptList,
                'receipts_count'     => count($receiptList),
                'total_receipts'     => $totalReceiptsAmount,
                'cash_receipts'      => $cashReceiptsAmount,
                'bank_receipts'      => $bankReceiptsAmount,
                'payments'           => $paymentList,
                'payments_list'      => $paymentList,
                'payments_count'     => count($paymentList),
                'total_payments'     => $totalPaymentsAmount,
                'cash_payments'      => $cashPaymentsAmount,
                'bank_payments'      => $bankPaymentsAmount,
                'net_cash_flow'      => $netDailyCashFlow,
            ],
            'pipeline'           => [
                'quotations'         => $quotationList,
                'quotations_list'    => $quotationList,
                'quotations_count'   => $quotationsCount,
                'quotations_total'   => $quotationsTotal,
                'sales_orders'       => $salesOrderList,
                'sales_orders_list'  => $salesOrderList,
                'sales_orders_count' => $salesOrdersCount,
                'sales_orders_total' => $salesOrdersTotal,
            ],
            'sales_pipeline'     => [
                'quotations_list'    => $quotationList,
                'sales_orders_list'  => $salesOrderList,
            ],
            'procurement'        => [
                'list'               => $inwardList,
                'inwards'            => $inwardList,
                'inwards_count'      => $inwardReceiptsCount,
                'total_weight'       => $totalInwardNetWeight,
            ]
        ];
    }

    public function targetName(array $params): string
    {
        return 'Overall Daily Business & Operations MIS Report';
    }
}