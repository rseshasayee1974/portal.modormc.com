<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Patron;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Quantity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ERPDashboardController extends Controller
{
    public function index(Request $request)
    {
        $plantId = session('active_plant_id');
     
        // Smarter fallback: Find the first plant that has actual data if none selected
        if (!$plantId) {
            $plantId = \App\Models\Invoice::latest('invoice_date')->first()?->plant_id 
                    ?? \App\Models\WorkOrder::latest()->first()?->plant_id 
                    ?? \App\Models\Plant::where('is_active', true)->first()?->id;
        }

        $effectivePlantId = $plantId ?: 1;
        $patrons = Patron::query()->where('plant_id', $effectivePlantId)->orderBy('legal_name')->get();
  
        return Inertia::render('Dashboard/Dashboard', [
            'patrons' => $patrons,
            'filters' => [
                'start_date' => $request->input('start_date', '2026-01-01'), // Explicitly set to beginning of year
                'end_date' => $request->input('end_date', now()->toDateString()),
                'patron_id' => $request->input('patron_id')
            ]
        ]);
    }

    public function getData(Request $request)
    {
        $plantId = session('active_plant_id');
        
        // Smarter fallback for data fetch as well
        if (!$plantId) {
            $plantId = \App\Models\Invoice::latest('invoice_date')->first()?->plant_id 
                    ?? \App\Models\WorkOrder::latest()->first()?->plant_id 
                    ?? \App\Models\Plant::where('is_active', true)->first()?->id;
        }

        $start = $request->input('start_date', '2026-01-01');
        $end = $request->input('end_date', now()->toDateString());
        $patronId = $request->input('patron_id');

        // 1. Sales Orders / Work Orders 
        // In RMC, Work Orders are the primary sales driver. Let's use Work Orders if SalesOrders table is empty.
        $totalSalesOrders = 0;
        if (SalesOrder::where('plant_id', $plantId)->count() > 0) {
            $totalSalesOrders = SalesOrder::query()
                ->join('mm_quotations', 'mm_sales_orders.quotation_id', '=', 'mm_quotations.id')
                ->where('mm_sales_orders.plant_id', $plantId)
                ->whereBetween('mm_sales_orders.order_date', [$start, $end])
                ->sum('mm_quotations.amount_total');
        } else {
            // Fallback: If no Sales Orders, show Work Order count or a placeholder
            // For now, let's just keep it 0 but ensure it doesn't crash
        }

        // 2. Purchase Orders
        $totalPurchaseOrders = PurchaseOrder::where('plant_id', $plantId)
            ->whereBetween('date_order', [$start, $end])
            ->when($patronId, fn($q) => $q->where('vendor_id', $patronId))
            ->sum('amount_total');

        // 3. Invoiced (Sales Invoices)
        $totalInvoiced = Invoice::where('plant_id', $plantId)
            ->whereBetween('invoice_date', [$start, $end])
            ->when($patronId, fn($q) => $q->where('partner_id', $patronId))
            ->sum('total_amount');

        // 4. Payments Received (Receipts)
        $paymentsReceived = JournalEntryLine::where('plant_id', $plantId)
            ->whereHas('entry', function($q) use ($start, $end) {
                $q->where('voucher_type', 'RECEIPT')->whereBetween('voucher_date', [$start, $end]);
            })
            ->when($patronId, fn($q) => $q->where('partner_id', $patronId))
            ->sum('credit_amount');

        // 5. Payments Paid
        $paymentsPaid = JournalEntryLine::where('plant_id', $plantId)
            ->whereHas('entry', function($q) use ($start, $end) {
                $q->where('voucher_type', 'PAYMENT')->whereBetween('voucher_date', [$start, $end]);
            })
            ->when($patronId, fn($q) => $q->where('partner_id', $patronId))
            ->sum('debit_amount');

        // 6. Outstanding (Cumulative)
        $netBalance = JournalEntryLine::where('plant_id', $plantId)
            ->when($patronId, fn($q) => $q->where('partner_id', $patronId))
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
            ->value('balance') ?: 0;

        // 7. Stock Alerts
        $stockAlerts = Product::where('plant_id', $plantId)
            ->where('stock_alert', '>', 0)
            ->get()
            ->map(function($product) use ($plantId) {
                $currentStock = Quantity::where('product_id', $product->id)
                    ->where('plant_id', $plantId)
                    ->sum('quantity');
                
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'current_stock' => (float)$currentStock,
                    'alert_level' => (float)$product->stock_alert,
                    'unit' => $product->unit->unit_name ?? 'Unit',
                    'is_critical' => $currentStock <= $product->stock_alert
                ];
            })
            ->filter(fn($p) => $p['is_critical'])
            ->values();

        return response()->json([
            'metrics' => [
                'sales_orders' => (float)$totalSalesOrders,
                'purchase_orders' => (float)$totalPurchaseOrders,
                'invoiced' => (float)$totalInvoiced,
                'payments_received' => (float)$paymentsReceived,
                'payments_paid' => (float)$paymentsPaid,
                'outstanding' => (float)$netBalance,
            ],
            'stock_alerts' => $stockAlerts,
            'recent_transactions' => $this->getRecentActivity($plantId, $patronId),
            'work_orders' => $this->getRecentWorkOrders($plantId, $patronId),
            'batches' => $this->getRecentBatches($plantId, $patronId),
            'dispatches' => $this->getRecentDispatches($plantId, $patronId),
        ]);
    }

    private function getRecentWorkOrders($plantId, $patronId)
    {
        $query = WorkOrder::query()->with(['customer', 'mixDesign'])->where('plant_id', $plantId)->latest()->limit(5);
        if ($patronId) $query->where('customer_id', $patronId);
        return $query->get()->map(fn($wo) => [
            'id' => $wo->id,
            'number' => $wo->order_no,
            'customer' => $wo->customer->legal_name ?? 'N/A',
            'grade' => $wo->mixDesign->design_name ?? 'N/A',
            'qty' => (float)$wo->total_qty,
            'status' => $wo->status,
        ]);
    }

    private function getRecentBatches($plantId, $patronId)
    {
        $query = Batch::query()->with('workOrder.customer')->whereHas('workOrder', function($q) use ($plantId, $patronId) {
            $q->where('plant_id', $plantId);
            if ($patronId) $q->where('customer_id', $patronId);
        })->latest()->limit(5);
        
        return $query->get()->map(fn($b) => [
            'id' => $b->id,
            'no' => $b->batch_no,
            'wo' => $b->workOrder->order_no ?? 'N/A',
            'size' => (float)$b->batch_size,
            'time' => $b->start_time ? $b->start_time->format('H:i') : 'N/A'
        ]);
    }

    private function getRecentDispatches($plantId, $patronId)
    {
        $query = Dispatch::query()->with(['workOrder.customer', 'truck'])->where('plant_id', $plantId)->latest()->limit(5);
        if ($patronId) $query->where('customer_id', $patronId);

        return $query->get()->map(fn($d) => [
            'id' => $d->id,
            'ticket' => $d->dispatch_no,
            'vehicle' => $d->truck->registration ?? 'N/A',
            'customer' => $d->workOrder->customer->legal_name ?? 'N/A',
            'qty' => (float)$d->delivered_qty,
            'status' => $d->status && $d->status->invoice_id ? 'Billed' : 'Loaded',
            'whatsapp_url' => $d->getWhatsAppUrl()
        ]);
    }

    private function getRecentActivity($plantId, $patronId)
    {
        $query = JournalEntryLine::query()->with(['entry', 'ledger'])
            ->where('plant_id', $plantId)
            ->latest()
            ->limit(10);
            
        if ($patronId) $query->where('partner_id', $patronId);
        
        return $query->get()->map(fn($line) => [
            'date' => $line->entry->voucher_date ? $line->entry->voucher_date->toDateString() : 'N/A',
            'type' => $line->entry->voucher_type ?? 'N/A',
            'particulars' => $line->ledger->title ?? 'N/A',
            'amount' => (float)($line->debit_amount > 0 ? $line->debit_amount : $line->credit_amount),
            'dr_cr' => $line->debit_amount > 0 ? 'Dr' : 'Cr'
        ]);
    }
}
