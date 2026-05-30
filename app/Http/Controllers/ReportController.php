<?php

namespace App\Http\Controllers;

use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Models\Patron;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Dispatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\Reports\SalesRegisterService;
use App\Services\Reports\PurchaseRegisterService;
use Illuminate\Support\Facades\Cache;
use App\Services\SCM\InventoryValuationService;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $plantId = session('active_plant_id');
        $ledgers = Ledger::where('plant_id', $plantId)->orderBy('title')->get();
        $patrons = Patron::where('plant_id', $plantId)->orderBy('legal_name')->get();

        return Inertia::render('Reports/Index', [
            'ledgers' => $ledgers,
            'patrons' => $patrons,
            'filters' => [
                'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
                'end_date' => $request->input('end_date', now()->toDateString()),
            ]
        ]);
    }

    public function generate(Request $request)
    {
        $type = $request->input('type');
        $id = $request->input('id');
        $patronId = $request->input('patron_id');
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $export = $request->input('export');
        $plantId = session('active_plant_id');

        switch ($type) {
            case 'ledger':
                $data = $this->getLedgerReportData($id, $patronId, $start, $end);
                $targetName = $id ? Ledger::find($id)->title : 'All Ledgers Consolidated';
                break;
            case 'patron':
                $data = $this->getPatronReportData($patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Patrons / Global Summary';
                break;
            case 'purchase':
                $data = $this->getPurchaseReportData($patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Vendor Purchases';
                break;
            case 'sales':
                $data = $this->getSalesReportData($patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Customer Sales';
                break;
            case 'payment':
                $data = $this->getVoucherReportData('PAYMENT', $patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Payment Vouchers';
                break;
            case 'receipt':
                $data = $this->getVoucherReportData('RECEIPT', $patronId, $start, $end);
                $targetName = $patronId ? Patron::find($patronId)->legal_name : 'All Receipt Vouchers';
                break;
            case 'inventory_stock':
                $data = $this->getInventoryStockData($start, $end);
                $targetName = 'Stock Level Inventory Report';
                break;
            case 'inventory_inward':
                $data = $this->getInventoryInwardData($start, $end);
                $targetName = 'Purchase Order Inward Report';
                break;
            case 'production_batch':
                $data = $this->getProductionBatchData($start, $end);
                $targetName = 'Batch Production Consumption Report';
                break;
            case 'machines_list':
                $data = $this->getMachinesListData();
                $targetName = 'Fleet & Machine Inventory List';
                break;
            case 'payroll_personnel':
                $data = $this->getPayrollPersonnelData();
                $targetName = 'Personnel & Payroll Directory';
                break;
            case 'silo_stock_valuation':
                $method = $request->input('valuation_method', 'FIFO');
                $data = $this->getSiloStockValuationData($start, $end, $method);
                $targetName = 'Silo Stock Valuation Report';
                break;
            default:
                return response()->json(['error' => 'Invalid report type'], 400);
        }

        if ($export === 'excel') {
            return $this->exportExcel($type, $start, $end, $data);
        }

        if ($export === 'pdf') {
            $consolidation = $request->input('consolidation', 'po');
            return $this->exportPdf($type, $targetName, $start, $end, $data, $id, $patronId, $consolidation);
        }

        return response()->json($data);
    }

    private function getLedgerReportData($ledgerId, $patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = JournalEntryLine::where('plant_id', $plantId);
        
        if ($ledgerId) $query->where('account_id', $ledgerId);
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $openingBalance = (clone $query)
            ->whereHas('entry', function($q) use ($start) {
                $q->where('voucher_date', '<', $start);
            })
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query->with(['entry.lines.ledger', 'entry.lines.partner', 'ledger'])
            ->whereHas('entry', function($q) use ($start, $end) {
                $q->whereBetween('voucher_date', [$start, $end]);
            })
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function($line) use ($ledgerId) {
                $isDebit = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id);
                $particulars = '';
                
                if ($oppositeLines->count() == 1) {
                    $opp = $oppositeLines->first();
                    $particulars = ($isDebit ? 'To ' : 'By ') . ($opp->ledger->title ?? $opp->partner->legal_name ?? 'Unknown');
                } else {
                    $particulars = ($isDebit ? 'To ' : 'By ') . "As per details";
                }

                // If viewing All Ledgers, prepend the ledger name to particulars
                $ledgerNamePrefix = (!$ledgerId) ? '[' . ($line->ledger->title ?? 'N/A') . '] ' : '';

                return [
                    'date' => $line->entry->voucher_date->toDateString(),
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no' => $line->entry->voucher_number,
                    'narration' => $ledgerNamePrefix . $particulars . ' (' . ($line->line_narration ?: $line->entry->narration) . ')',
                    'amount' => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type' => $isDebit ? 'Dr' : 'Cr',
                    'debit' => (float)$line->debit_amount,
                    'credit' => (float)$line->credit_amount,
                ];
            })->values();

        return ['opening_balance' => (float)$openingBalance, 'transactions' => $transactions];
    }

    private function getPatronReportData($patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = JournalEntryLine::where('plant_id', $plantId);
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $openingBalance = (clone $query)
            ->whereHas('entry', function($q) use ($start) {
                $q->where('voucher_date', '<', $start);
            })
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        $transactions = $query->with(['entry.lines.ledger', 'partner'])
            ->whereHas('entry', function($q) use ($start, $end) {
                $q->whereBetween('voucher_date', [$start, $end]);
            })
            ->get()
            ->sortBy(fn($line) => $line->entry->voucher_date . $line->entry->id)
            ->map(function($line) {
                $isDebit = $line->debit_amount > 0;
                $oppositeLines = $line->entry->lines->filter(fn($l) => $l->id != $line->id);
                $particulars = '';
                if ($oppositeLines->count() == 1) {
                    $opp = $oppositeLines->first();
                    $particulars = ($isDebit ? 'To ' : 'By ') . ($opp->ledger->title ?? 'General Account');
                } else {
                    $particulars = ($isDebit ? 'To ' : 'By ') . "As per details";
                }

                $patronNamePrefix = (!$line->partner_id) ? '[' . ($line->partner->legal_name ?? 'N/A') . '] ' : '';

                return [
                    'date' => $line->entry->voucher_date->toDateString(),
                    'due_date' => $line->entry->due_date ? $line->entry->due_date->toDateString() : null,
                    'voucher_type' => $line->entry->voucher_type,
                    'voucher_no' => $line->entry->voucher_number,
                    'narration' => $patronNamePrefix . $particulars . ' (' . ($line->line_narration ?: $line->entry->narration) . ')',
                    'amount' => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                    'type' => $isDebit ? 'Dr' : 'Cr',
                    'debit' => (float)$line->debit_amount,
                    'credit' => (float)$line->credit_amount,
                ];
            })->values();

        // Account Summary Queries
        $invoicedTaxTotal = 0;
        $invoicedNonTaxTotal = 0;
        $salesDiscount = 0;
        $purchased = 0;
        $amountReceived = 0;
        $amountPaid = 0;

        if ($patronId) {
            // Fetch Sales Invoices within period
            $invoices = Invoice::where('partner_id', $patronId)
                ->where('plant_id', $plantId)
                ->whereBetween('invoice_date', [$start, $end])
                ->get();
            
            foreach ($invoices as $inv) {
                if ($inv->tax_amount > 0) {
                    $invoicedTaxTotal += $inv->total_amount;
                } else {
                    $invoicedNonTaxTotal += $inv->total_amount;
                }
                $salesDiscount += ($inv->discount_amount ?? 0);
            }

            // Fetch Purchases
            $purchased = \App\Models\PurchaseOrder::where('vendor_id', $patronId)
                ->where('plant_id', $plantId)
                ->whereBetween('date_order', [$start, $end])
                ->sum('amount_total');

            // Fetch Receipts (Amount Received)
            $amountReceived = JournalEntryLine::where('plant_id', $plantId)
                ->where('partner_id', $patronId)
                ->where('partner_type', 'Patron')
                ->whereHas('entry', function($q) use ($start, $end) {
                    $q->where('voucher_type', 'RECEIPT')->whereBetween('voucher_date', [$start, $end]);
                })
                ->sum('credit_amount');

            // Fetch Payments (Amount Paid)
            $amountPaid = JournalEntryLine::where('plant_id', $plantId)
                ->where('partner_id', $patronId)
                ->where('partner_type', 'Patron')
                ->whereHas('entry', function($q) use ($start, $end) {
                    $q->where('voucher_type', 'PAYMENT')->whereBetween('voucher_date', [$start, $end]);
                })
                ->sum('debit_amount');
        }

        return [
            'opening_balance' => (float)$openingBalance,
            'transactions' => $transactions,
            'invoiced_tax' => (float)$invoicedTaxTotal,
            'invoiced_nontax' => (float)$invoicedNonTaxTotal,
            'sales_discount' => (float)$salesDiscount,
            'purchased' => (float)$purchased,
            'amount_received' => (float)$amountReceived,
            'amount_paid' => (float)$amountPaid,
            'credits' => 0.00
        ];
    }

    private function getPurchaseReportData($patronId, $start, $end)
    {
        $plantId = session('active_plant_id');

        // 1. Fetch Purchase Orders (PO-wise transactions)
        $poQuery = PurchaseOrder::with(['vendor'])->where('plant_id', $plantId)->whereBetween('date_order', [$start, $end]);
        if ($patronId) $poQuery->where('vendor_id', $patronId);

        $orders = $poQuery->orderBy('date_order')->orderBy('po_number')->get();

        $bills = $orders->map(fn($po) => [
            'date' => $po->date_order->toDateString(),
            'voucher_type' => 'PURCHASE',
            'voucher_no' => $po->po_number,
            'po_number' => $po->po_number,
            'vendor_name' => $po->vendor->legal_name ?? 'N/A',
            'narration' => '[' . ($po->vendor->legal_name ?? 'Vendor') . '] Purchase Bill',
            'amount' => (float)$po->amount_total,
            'amount_total' => (float)$po->amount_total,
            'amount_untaxed' => (float)$po->amount_untaxed,
            'amount_tax' => (float)$po->amount_tax,
            'type' => 'Dr',
            'debit' => (float)$po->amount_total,
            'credit' => 0,
        ]);

        // 2. Fetch Product-wise items
        $itemQuery = PurchaseOrderItem::whereHas('order', function($q) use ($plantId, $start, $end, $patronId) {
            $q->where('plant_id', $plantId)->whereBetween('date_order', [$start, $end]);
            if ($patronId) $q->where('vendor_id', $patronId);
        })->with(['product', 'uom']);

        $items = $itemQuery->get();

        $grouped = $items->groupBy('product_id')->map(function($productItems) {
            $first = $productItems->first();
            $productName = $first->product->title ?? 'Unknown Product';
            $uomName = $first->uom->name ?? $first->uom->code ?? 'Unit';
            $totalQty = (float)$productItems->sum('product_quantity');
            $totalUntaxed = (float)$productItems->sum('price_subtotal');
            $totalTax = (float)$productItems->sum('price_tax');
            $totalTotal = (float)$productItems->sum('price_total');
            $avgRate = $totalQty > 0 ? ($totalUntaxed / $totalQty) : 0.00;

            return [
                'product_name' => $productName,
                'uom' => $uomName,
                'quantity' => $totalQty,
                'avg_rate' => $avgRate,
                'amount_untaxed' => $totalUntaxed,
                'amount_tax' => $totalTax,
                'amount_total' => $totalTotal,
            ];
        })->values()->sortBy('product_name')->values();

        return [
            'opening_balance' => 0,
            'transactions' => $bills,
            'total_untaxed' => (float)$orders->sum('amount_untaxed'),
            'total_tax' => (float)$orders->sum('amount_tax'),
            'total_amount' => (float)$orders->sum('amount_total'),
            
            // Product summary additions
            'product_summary' => $grouped,
            'total_quantity' => (float)$grouped->sum('quantity'),
            'total_product_untaxed' => (float)$grouped->sum('amount_untaxed'),
            'total_product_tax' => (float)$grouped->sum('amount_tax'),
            'total_product_amount' => (float)$grouped->sum('amount_total'),
        ];
    }

    private function getSalesReportData($patronId, $start, $end)
    {
        $plantId = session('active_plant_id');

        // 1. Fetch Invoices (Sales details)
        $invoiceQuery = Invoice::with(['partner'])->where('plant_id', $plantId)->whereBetween('invoice_date', [$start, $end]);
        if ($patronId) $invoiceQuery->where('partner_id', $patronId);

        $invoicesList = $invoiceQuery->orderBy('invoice_date')->orderBy('invoice_number')->get();

        $transactions = $invoicesList->map(fn($inv) => [
            'date' => $inv->invoice_date->toDateString(),
            'voucher_type' => 'SALES',
            'voucher_no' => ($inv->prefix ?? '') . ($inv->invoice_number ?? ''),
            'invoice_number' => ($inv->prefix ?? '') . ($inv->invoice_number ?? ''),
            'customer_name' => $inv->partner->legal_name ?? 'N/A',
            'narration' => '[' . ($inv->partner->legal_name ?? 'Customer') . '] Sales Invoice',
            'amount' => (float)$inv->total_amount,
            'amount_total' => (float)$inv->total_amount,
            'amount_untaxed' => (float)$inv->subtotal,
            'amount_tax' => (float)$inv->tax_amount,
            'type' => 'Cr',
            'debit' => 0,
            'credit' => (float)$inv->total_amount,
        ]);

        // 2. Fetch Invoice Items (Product-wise Consolidated Summary)
        $itemQuery = InvoiceItem::whereHas('invoice', function($q) use ($plantId, $start, $end, $patronId) {
            $q->where('plant_id', $plantId)->whereBetween('invoice_date', [$start, $end]);
            if ($patronId) $q->where('partner_id', $patronId);
        })->with(['uom']);

        $items = $itemQuery->get();

        $groupedProducts = $items->groupBy('item_name')->map(function($invoiceItems) {
            $first = $invoiceItems->first();
            $itemName = $first->item_name ?? 'Unknown Item';
            $uomName = $first->uom->name ?? $first->uom->code ?? 'Unit';
            $totalQty = (float)$invoiceItems->sum('quantity');
            $totalUntaxed = (float)$invoiceItems->sum('subtotal');
            $totalTax = (float)$invoiceItems->sum('line_tax_amount');
            $totalTotal = (float)$invoiceItems->sum('line_total');
            $avgRate = $totalQty > 0 ? ($totalUntaxed / $totalQty) : 0.00;

            return [
                'product_name' => $itemName,
                'uom' => $uomName,
                'quantity' => $totalQty,
                'avg_rate' => $avgRate,
                'amount_untaxed' => $totalUntaxed,
                'amount_tax' => $totalTax,
                'amount_total' => $totalTotal,
            ];
        })->values()->sortBy('product_name')->values();

        // 3. Fetch Dispatches (Dispatch Consolidated Summary)
        $dispatchQuery = Dispatch::with(['customer', 'mixDesign.unit'])
            ->where('plant_id', $plantId)
            ->whereBetween('dispatch_time', [$start . ' 00:00:00', $end . ' 23:59:59']);
        if ($patronId) $dispatchQuery->where('customer_id', $patronId);

        $dispatches = $dispatchQuery->get();

        // 3.1 Mix Design & Concrete Grade wise Consolidated Overall
        $mixDesignSummary = $dispatches->groupBy('mixdesign_id')->map(function($dispatchItems) {
            $first = $dispatchItems->first();
            $mixName = $first->mixDesign?->design_name ?? 'Unknown Mix';
            $gradeName = $first->mixDesign?->grade ?? $first->mixDesign?->design_type ?? 'N/A';
            $uomName = $first->mixDesign?->unit?->name ?? 'm³';
            $totalQty = (float)$dispatchItems->sum('delivered_qty');
            $totalUntaxed = (float)$dispatchItems->sum('load_untax_amount');
            $totalTax = (float)$dispatchItems->sum('load_tax_amount');
            $totalTotal = (float)$dispatchItems->sum('load_total_amount');
            $avgRate = $totalQty > 0 ? ($totalUntaxed / $totalQty) : 0.00;

            return [
                'mix_name' => $mixName,
                'concrete_grade' => $gradeName,
                'uom' => $uomName,
                'quantity' => $totalQty,
                'avg_rate' => $avgRate,
                'amount_untaxed' => $totalUntaxed,
                'amount_tax' => $totalTax,
                'amount_total' => $totalTotal,
            ];
        })->values()->sortBy('mix_name')->values();

        // 3.2 Party (Customer) wise Consolidated Summary
        $partySummary = $dispatches->groupBy('customer_id')->map(function($dispatchItems) {
            $first = $dispatchItems->first();
            $partyName = $first->customer?->legal_name ?? 'Unknown Customer';
            $totalQty = (float)$dispatchItems->sum('delivered_qty');
            $totalUntaxed = (float)$dispatchItems->sum('load_untax_amount');
            $totalTax = (float)$dispatchItems->sum('load_tax_amount');
            $totalTotal = (float)$dispatchItems->sum('load_total_amount');

            return [
                'party_name' => $partyName,
                'quantity' => $totalQty,
                'amount_untaxed' => $totalUntaxed,
                'amount_tax' => $totalTax,
                'amount_total' => $totalTotal,
            ];
        })->values()->sortBy('party_name')->values();

        return [
            'opening_balance' => 0,
            'transactions' => $transactions,
            'total_untaxed' => (float)$invoicesList->sum('subtotal'),
            'total_tax' => (float)$invoicesList->sum('tax_amount'),
            'total_amount' => (float)$invoicesList->sum('total_amount'),
            
            // Product summary additions
            'product_summary' => $groupedProducts,
            'total_quantity' => (float)$groupedProducts->sum('quantity'),
            'total_product_untaxed' => (float)$groupedProducts->sum('amount_untaxed'),
            'total_product_tax' => (float)$groupedProducts->sum('amount_tax'),
            'total_product_amount' => (float)$groupedProducts->sum('amount_total'),

            // Dispatch summaries
            'mix_design_summary' => $mixDesignSummary,
            'total_dispatch_quantity' => (float)$mixDesignSummary->sum('quantity'),
            'total_dispatch_untaxed' => (float)$mixDesignSummary->sum('amount_untaxed'),
            'total_dispatch_tax' => (float)$mixDesignSummary->sum('amount_tax'),
            'total_dispatch_amount' => (float)$mixDesignSummary->sum('amount_total'),

            'party_summary' => $partySummary,
            'total_party_quantity' => (float)$partySummary->sum('quantity'),
            'total_party_untaxed' => (float)$partySummary->sum('amount_untaxed'),
            'total_party_tax' => (float)$partySummary->sum('amount_tax'),
            'total_party_amount' => (float)$partySummary->sum('amount_total'),
        ];
    }

    private function getVoucherReportData($voucherType, $patronId, $start, $end)
    {
        $plantId = session('active_plant_id');
        $query = JournalEntryLine::where('plant_id', $plantId)
            ->whereHas('entry', function($q) use ($voucherType, $start, $end) {
                $q->where('voucher_type', $voucherType)->whereBetween('voucher_date', [$start, $end]);
            });
            
        if ($patronId) $query->where('partner_id', $patronId)->where('partner_type', 'Patron');

        $transactions = $query->with(['entry', 'ledger', 'partner'])->get()->map(function($line) {
            $isDebit = $line->debit_amount > 0;
            $partnerPrefix = $line->partner ? '[' . $line->partner->legal_name . '] ' : '[' . ($line->ledger->title ?? 'N/A') . '] ';
            return [
                'date' => $line->entry->voucher_date->toDateString(),
                'voucher_type' => $line->entry->voucher_type,
                'voucher_no' => $line->entry->voucher_number,
                'narration' => $partnerPrefix . ($line->line_narration ?: $line->entry->narration),
                'amount' => $isDebit ? (float)$line->debit_amount : (float)$line->credit_amount,
                'type' => $isDebit ? 'Dr' : 'Cr',
                'debit' => (float)$line->debit_amount,
                'credit' => (float)$line->credit_amount,
            ];
        });

        return ['opening_balance' => 0, 'transactions' => $transactions];
    }

    private function exportPdf($type, $targetName, $start, $end, $data, $ledgerId = null, $patronId = null, $consolidation = 'po')
    {
        $viewMap = [
            'LEDGER' => 'reports.ledger_report',
            'PATRON' => 'reports.patron_report',
            'SALES' => 'reports.sales_report',
            'PURCHASE' => 'reports.purchase_report',
            'PAYMENT' => 'reports.payment_report',
            'RECEIPT' => 'reports.receipt_report',
            'INVENTORY_STOCK' => 'reports.generic_report',
            'INVENTORY_INWARD' => 'reports.generic_report',
            'PRODUCTION_BATCH' => 'reports.generic_report',
            'MACHINES_LIST' => 'reports.generic_report',
            'PAYROLL_PERSONNEL' => 'reports.generic_report',
            'SILO_STOCK_VALUATION' => 'reports.generic_report',
        ];

        $view = $viewMap[strtoupper($type)] ?? 'reports.ledger_report';

        $plantId = session('active_plant_id');
        $plant = \App\Models\Plant::with(['addresses.state', 'contacts'])->find($plantId);

        $patron = null;
        if ($patronId) {
            $patron = \App\Models\Patron::with(['addresses.state'])->find($patronId);
        } elseif ($ledgerId) {
            $patron = \App\Models\Patron::with(['addresses.state'])->where('ledger_id', $ledgerId)->first();
        }

        $extraParams = [];
        if (str_contains(strtolower($type), 'inventory_stock')) {
            $extraParams = [
                'headers' => ['Date', 'Product Name', 'UOM', 'Opening Qty', 'Current Stock', 'Status'],
                'fields' => ['date', 'product_name', 'uom', 'opening_qty', 'quantity', 'status'],
                'alignments' => ['center', 'left', 'center', 'right', 'right', 'center'],
                'totals' => ['quantity' => $data['total_quantity'] ?? 0]
            ];
        } elseif (str_contains(strtolower($type), 'inventory_inward')) {
            $extraParams = [
                'headers' => ['Received Date', 'Inward No', 'PO No', 'Supplier Name', 'Product', 'Quantity', 'Truck No'],
                'fields' => ['date', 'inward_no', 'po_number', 'vendor_name', 'product_name', 'quantity', 'truck_no'],
                'alignments' => ['center', 'center', 'center', 'left', 'left', 'right', 'center'],
                'totals' => ['quantity' => $data['total_quantity'] ?? 0]
            ];
        } elseif (str_contains(strtolower($type), 'production_batch')) {
            $extraParams = [
                'headers' => ['Start Date', 'Batch No', 'Work Order', 'Mix Design', 'Batch Size (m³)', 'Operator', 'Status'],
                'fields' => ['date', 'batch_no', 'work_order', 'mix_design', 'batch_size', 'operator', 'status'],
                'alignments' => ['center', 'center', 'center', 'left', 'right', 'left', 'center'],
                'totals' => ['batch_size' => $data['total_batch_size'] ?? 0]
            ];
        } elseif (str_contains(strtolower($type), 'machines_list')) {
            $extraParams = [
                'headers' => ['Registration', 'Vehicle Model', 'Vehicle Type', 'Make Year', 'Capacity', 'Owner'],
                'fields' => ['registration', 'vehicle_model', 'vehicle_type', 'make_year', 'capacity', 'owner'],
                'alignments' => ['center', 'left', 'center', 'center', 'right', 'left']
            ];
        } elseif (str_contains(strtolower($type), 'payroll_personnel')) {
            $extraParams = [
                'headers' => ['Name', 'Role / Employee Type', 'Joining Date', 'Status', 'Email', 'Phone'],
                'fields' => ['name', 'employee_type', 'joining_date', 'status', 'email', 'phone'],
                'alignments' => ['left', 'left', 'center', 'center', 'left', 'center']
            ];
        } elseif (str_contains(strtolower($type), 'silo_stock_valuation')) {
            $extraParams = [
                'headers' => ['Product Name', 'Category', 'UOM', 'Opening Qty', 'Opening Value', 'Inward Qty', 'Inward Value', 'Consumed Qty', 'COGS Value', 'Ending Qty', 'Ending Value', 'Avg Unit Cost'],
                'fields' => ['product_name', 'category', 'uom', 'opening_qty', 'opening_value_formatted', 'inward_qty', 'inward_value_formatted', 'consumed_qty', 'consumed_value_formatted', 'ending_qty', 'ending_value_formatted', 'avg_unit_cost_formatted'],
                'alignments' => ['left', 'left', 'center', 'right', 'right', 'right', 'right', 'right', 'right', 'right', 'right', 'right'],
                'totals' => [
                    'opening_value_formatted' => $data['total_opening_value_formatted'] ?? '₹ 0',
                    'inward_value_formatted' => $data['total_inward_value_formatted'] ?? '₹ 0',
                    'consumed_value_formatted' => $data['total_consumed_value_formatted'] ?? '₹ 0',
                    'ending_value_formatted' => $data['total_ending_value_formatted'] ?? '₹ 0',
                ]
            ];
        }

        $pdfData = array_merge([
            'type' => strtoupper($type),
            'target_name' => $targetName,
            'start' => \Carbon\Carbon::parse($start)->format('d-m-Y'),
            'end' => \Carbon\Carbon::parse($end)->format('d-m-Y'),
            'plant' => $plant,
            'patron' => $patron,
            'consolidation' => $consolidation
        ], $data, $extraParams);

        $pdf = Pdf::loadView($view, $pdfData)->setPaper('a4', 'portrait');

        return $pdf->download("Report_{$type}_{$start}.pdf");
    }

    private function exportExcel($type, $start, $end, $data)
    {
        $filename = "Report_{$type}_{$start}_to_{$end}.csv";
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];

        return response()->stream(function() use ($type, $start, $end, $data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ["Report Type:", strtoupper($type)]);
            fputcsv($file, ["Period:", "$start to $end"]);
            fputcsv($file, []);

            if ($type === 'silo_stock_valuation') {
                fputcsv($file, ['Product Name', 'Category', 'UOM', 'Opening Qty', 'Opening Value', 'Inward Qty', 'Inward Value', 'Consumed Qty', 'Consumed Value (COGS)', 'Ending Qty', 'Ending Value', 'Avg Unit Cost']);
                foreach ($data['transactions'] as $row) {
                    fputcsv($file, [
                        $row['product_name'],
                        $row['category'],
                        $row['uom'],
                        $row['opening_qty'],
                        $row['opening_value'],
                        $row['inward_qty'],
                        $row['inward_value'],
                        $row['consumed_qty'],
                        $row['consumed_value'],
                        $row['ending_qty'],
                        $row['ending_value'],
                        $row['avg_unit_cost']
                    ]);
                }
            } else {
                fputcsv($file, ['Date', 'Particulars', 'Voucher Type', 'Voucher No', 'Amount', 'Type', 'Balance']);

                $balance = $data['opening_balance'] ?? 0;
                if ($balance != 0) fputcsv($file, [$start, 'Opening Balance', '', '', abs($balance), $balance > 0 ? 'Dr' : 'Cr', $balance]);

                foreach ($data['transactions'] as $row) {
                    $balance += ($row['debit'] - $row['credit']);
                    fputcsv($file, [$row['date'], $row['narration'], $row['voucher_type'], $row['voucher_no'], $row['amount'], $row['type'], $balance]);
                }
            }
            fclose($file);
        }, 200, $headers);
    }

    private function getInventoryStockData($start, $end)
    {
        $plantId = session('active_plant_id');
        $stocks = \App\Models\Quantity::where('plant_id', $plantId)
            ->with(['product', 'uom'])
            ->whereBetween('date', [$start, $end])
            ->get();

        return [
            'transactions' => $stocks->map(fn($s) => [
                'date' => $s->date->toDateString(),
                'product_name' => $s->product->title ?? 'N/A',
                'uom' => $s->uom->name ?? 'N/A',
                'opening_qty' => (float)$s->opening_quantity,
                'quantity' => (float)$s->quantity,
                'status' => $s->status ? 'Active' : 'Inactive',
            ])->values(),
            'total_quantity' => (float)$stocks->sum('quantity'),
            'opening_balance' => 0
        ];
    }

    private function getInventoryInwardData($start, $end)
    {
        $plantId = session('active_plant_id');
        $inwards = \App\Models\PurchaseOrderHistory::where('plant_id', $plantId)
            ->with(['order.vendor', 'product', 'uom', 'truck'])
            ->whereBetween('received_date', [$start, $end])
            ->get();

        return [
            'transactions' => $inwards->map(fn($i) => [
                'date' => $i->received_date,
                'inward_no' => $i->inward_no,
                'po_number' => $i->order->po_number ?? 'N/A',
                'vendor_name' => $i->order->vendor->legal_name ?? 'N/A',
                'product_name' => $i->product->title ?? 'N/A',
                'uom' => $i->uom->name ?? 'N/A',
                'quantity' => (float)$i->received_qty,
                'truck_no' => $i->truck->registration ?? 'N/A',
                'truck_loaded' => (float)$i->truck_loaded,
                'truck_empty' => (float)$i->truck_empty,
            ])->values(),
            'total_quantity' => (float)$inwards->sum('received_qty'),
            'opening_balance' => 0
        ];
    }

    private function getProductionBatchData($start, $end)
    {
        $plantId = session('active_plant_id');
        $batches = \App\Models\Batch::where('plant_id', $plantId)
            ->with(['operator', 'workOrder.mixDesign', 'materials.product'])
            ->whereBetween('start_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->get();

        $materialSummary = [];
        foreach ($batches as $batch) {
            foreach ($batch->materials as $mat) {
                $matName = $mat->material_name ?: ($mat->product->title ?? 'Unknown Material');
                if (!isset($materialSummary[$matName])) {
                    $materialSummary[$matName] = [
                        'material_name' => $matName,
                        'target_qty' => 0.0,
                        'actual_qty' => 0.0,
                    ];
                }
                $materialSummary[$matName]['target_qty'] += (float)$mat->target_qty;
                $materialSummary[$matName]['actual_qty'] += (float)$mat->actual_qty;
            }
        }

        return [
            'transactions' => $batches->map(fn($b) => [
                'date' => $b->start_time->toDateString(),
                'batch_no' => $b->batch_no,
                'work_order' => $b->workOrder->wo_number ?? 'N/A',
                'mix_design' => $b->workOrder->mixDesign->design_name ?? 'N/A',
                'batch_size' => (float)$b->batch_size,
                'operator' => $b->operator->first_name ?? 'N/A',
                'status' => \App\Models\Batch::statusLabel($b->status),
            ])->values(),
            'material_summary' => array_values($materialSummary),
            'total_batch_size' => (float)$batches->sum('batch_size'),
            'opening_balance' => 0
        ];
    }

    private function getMachinesListData()
    {
        $plantId = session('active_plant_id');
        $machines = \App\Models\Machine::where('plant_id', $plantId)
            ->with(['owner'])
            ->get();

        return [
            'transactions' => $machines->map(fn($m) => [
                'registration' => $m->registration,
                'vehicle_model' => $m->vehicle_model ?? 'N/A',
                'vehicle_type' => $m->vehicle_type ?? 'N/A',
                'make_year' => $m->make_year ?? 'N/A',
                'capacity' => $m->capacity ?? 'N/A',
                'owner' => $m->owner->legal_name ?? 'Self/Company Owned',
            ])->values(),
            'opening_balance' => 0
        ];
    }

    private function getPayrollPersonnelData()
    {
        $plantId = session('active_plant_id');
        $personnel = \App\Models\Personnel::where('plant_id', $plantId)
            ->with(['user', 'contacts'])
            ->get();

        return [
            'transactions' => $personnel->map(fn($p) => [
                'name' => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
                'employee_type' => $p->employee_type ?? 'N/A',
                'joining_date' => $p->joining_date ? \Carbon\Carbon::parse($p->joining_date)->toDateString() : 'N/A',
                'status' => $p->status ? 'Active' : 'Inactive',
                'email' => $p->user->email ?? 'N/A',
                'phone' => $p->contacts->first()->contact_value ?? 'N/A',
            ])->values(),
            'opening_balance' => 0
        ];
    }

    private function getSiloStockValuationData($start, $end, $method)
    {
        $plantId = session('active_plant_id');
        $service = new \App\Services\SCM\InventoryValuationService();
        $result = $service->calculate($plantId, $start, $end, $method);

        $formattedProducts = [];
        $totalOpeningVal = 0.0;
        $totalInwardVal = 0.0;
        $totalConsumedVal = 0.0;
        $totalEndingVal = 0.0;

        foreach ($result['products'] as $p) {
            $totalOpeningVal += $p['opening_value'];
            $totalInwardVal += $p['inward_value'];
            $totalConsumedVal += $p['consumed_value'];
            $totalEndingVal += $p['ending_value'];

            $p['opening_value_formatted'] = '₹ ' . number_format($p['opening_value'], 2);
            $p['inward_value_formatted'] = '₹ ' . number_format($p['inward_value'], 2);
            $p['consumed_value_formatted'] = '₹ ' . number_format($p['consumed_value'], 2);
            $p['ending_value_formatted'] = '₹ ' . number_format($p['ending_value'], 2);
            $p['avg_unit_cost_formatted'] = '₹ ' . number_format($p['avg_unit_cost'], 2);

            $formattedProducts[] = $p;
        }

        return [
            'transactions' => $formattedProducts,
            'products' => $formattedProducts,
            'total_opening_value_formatted' => '₹ ' . number_format($totalOpeningVal, 2),
            'total_inward_value_formatted' => '₹ ' . number_format($totalInwardVal, 2),
            'total_consumed_value_formatted' => '₹ ' . number_format($totalConsumedVal, 2),
            'total_ending_value_formatted' => '₹ ' . number_format($totalEndingVal, 2),
            'opening_balance' => 0
        ];
    }

    /**
     * Generate optimized Sales Register Report.
     */
    public function salesRegister(Request $request, SalesRegisterService $service)
    {
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'gst_type' => 'nullable|string|in:intra,inter',
            'invoice_type' => 'nullable|string',
            'product_id' => 'nullable|integer',
            'salesman_id' => 'nullable|integer',
            'payment_status' => 'nullable|string|in:paid,unpaid,partial',
            'per_page' => 'nullable|integer|min:1|max:500',
            'export' => 'nullable|string|in:excel,pdf',
            'refresh' => 'nullable|boolean',
            'queue' => 'nullable|boolean'
        ]);

        $response = $service->generate($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Generate optimized Purchase Register Report.
     */
    public function purchaseRegister(Request $request, PurchaseRegisterService $service)
    {
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id' => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'gst_type' => 'nullable|string|in:intra,inter',
            'product_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:500',
            'export' => 'nullable|string|in:excel,pdf',
            'refresh' => 'nullable|boolean',
            'queue' => 'nullable|boolean'
        ]);

        $response = $service->generate($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Get queued export job status.
     */
    public function getExportStatus(string $key)
    {
        $status = Cache::get($key);

        if (!$status) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Export job not found or expired.'
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * Generate Machine Summary Report.
     */
    public function machineSummary(Request $request, \App\Services\Reports\MachineReportService $service)
    {
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:500',
            'export' => 'nullable|string|in:excel,pdf',
            'refresh' => 'nullable|boolean',
            'queue' => 'nullable|boolean'
        ]);

        $response = $service->generateMachineSummary($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Generate Vehicle Wise Profit & Loss Report.
     */
    public function vehiclePL(Request $request, \App\Services\Reports\MachineReportService $service)
    {
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:500',
            'export' => 'nullable|string|in:excel,pdf',
            'refresh' => 'nullable|boolean',
            'queue' => 'nullable|boolean'
        ]);

        $response = $service->generateVehiclePL($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }
}
