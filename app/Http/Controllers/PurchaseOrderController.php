<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\Financialyear;
use App\Models\CustomSetting;

class PurchaseOrderController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'purchase_orders';

    public function index()
    {
        $this->authorizeModule('menu');

        $allowedPlantIds = session('active_plant_id');

        $purchaseOrders = PurchaseOrder::query()
            ->where('plant_id', $allowedPlantIds)
            ->with(['plant', 'vendor', 'currency', 'creator', 'items.product', 'items.uom', 'items.tax'])
            ->latest()
            ->get();

        $ref_no = Financialyear::generatePurchaseOrderRefNo(session('active_plant_id'));
// dd(toSelectOptions(LedgersDropdown(session('active_plant_id'), 'EXPENSE'), 'title'));
        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'ref_no'         => $ref_no,
            
            'accounts'      => toSelectOptions(LedgersDropdown('EXPENSE'), 'title'),
            'vendors'        => VendorsDropdown(['Vendor']),
            'vehicles'       => VehiclesDropdown(),
            'currencies'     => CurrenciesDropdown(),
            'taxes'          => TaxesDropdown('purchase', ['GST', 'IGST']),
            'products'       => ProductsDropdown('purchase'),
            'productUnits'   => Productunit(),
            'instant_vendor' => CustomSetting::getForModule(session('active_plant_id'), 'purchase')['instant_vendor'] ?? 0,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeModule('create');

        $plantId = session('active_plant_id');

        return Inertia::render('PurchaseOrders/Create', [
            'vendors'      => VendorsDropdown(['Vendor']),
            'vehicles'     => VehiclesDropdown(),
             
            'taxes'        => TaxesDropdown('purchase', ['GST', 'IGST']),
            'products'     => ProductsDropdown('purchase'),
            'productUnits' => Productunit('purchase'),
            'ref_no'       => Financialyear::generatePurchaseOrderRefNo($plantId),
            'instant_vendor' => CustomSetting::getForModule(session('active_plant_id'), 'purchase')['instant_vendor'] ?? 0,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $this->authorizeModule('create');

        $validatedData = $request->validated();
        
        $dateFields = ['date_order', 'date_planned', 'delivery_date', 'due_date'];
        foreach ($dateFields as $field) {
            if (!empty($validatedData[$field])) {
                $validatedData[$field] = \Carbon\Carbon::parse($validatedData[$field])->toDateString();
            }
        }

        PurchaseOrder::storeWithItems($validatedData);

        return redirect()->route('purchaseorder.index')
            ->with('success', 'Purchase Order created successfully.');
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeModule('edit');

        $this->authorizePlantAccess($purchaseOrder);
        $purchaseOrder->load(['items.product', 'items.uom', 'items.tax', 'items.history']);
 

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'vendors'       => VendorsDropdown(['Vendor']),
            'vehicles'      => VehiclesDropdown(),
            'currencies'    => CurrenciesDropdown(),
            'taxes'         => TaxesDropdown('purchase', ['GST', 'IGST']),
            'products'      => ProductsDropdown('purchase'),
            'productUnits'  => Productunit('purchase'),
            'accounts'      => toSelectOptions(LedgersDropdown('EXPENSE'), 'title'),
            'ref_no'        => Financialyear::generatePurchaseOrderRefNo($purchaseOrder->plant_id, $purchaseOrder->date_order?->toDateString()),
            'instant_vendor' => CustomSetting::getForModule(session('active_plant_id'), 'purchase')['instant_vendor'] ?? 0,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseorder)
    {
        $purchaseOrder = $purchaseorder; // Keep using the camelCase variable for consistency in the method body
        $this->authorizeModule('edit');
        $this->authorizePlantAccess($purchaseOrder);

        if ((int)$purchaseOrder->receipt_status > 0) {
            return redirect()->back()->with('error', 'Purchase Order cannot be modified as items have already been received.');
        }

        $validatedData = $request->validated();
        $dateFields = ['date_order', 'date_planned', 'delivery_date', 'due_date', 'billed_date'];
        foreach ($dateFields as $field) {
            if (!empty($validatedData[$field])) {
                $validatedData[$field] = \Carbon\Carbon::parse($validatedData[$field])->toDateString();
            }
        }

        $purchaseOrder->updateWithItems($validatedData);

        return redirect()->route('purchaseorder.index')
            ->with('success', 'Purchase Order updated successfully.');
    }

    public function generateBill(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorizeModule('edit');
        $this->authorizePlantAccess($purchase_order);

        // Check if already invoiced - look for any active (non-deleted) bill for this PO
        $existingBill = \App\Models\Invoice::where('ref_id', $purchase_order->id)
            ->where('invoice_type', 'bill')
            ->first();

        if ($existingBill || (int)$purchase_order->invoice_status === 1) {
            // If invoice_status is 1 but record is missing, it might have been deleted,
            // but we should still be cautious. If the record exists, we definitely block.
            if ($existingBill) {
                return redirect()->back()->with('error', 'A bill (' . $existingBill->invoice_number . ') has already been generated for this Purchase Order.');
            }
            
            // If status is 1 but no record found, it might be an inconsistent state.
            // For safety, we block if status is 1, unless the user specifically deleted the bill.
            return redirect()->back()->with('error', 'This Purchase Order is already marked as billed.');
        }

        // Use the common invoice generation function
        // This function already calls postToAccounting() if status is APPROVED
        $invoice = \App\Http\Controllers\InvoiceController::createFromSource($purchase_order, 'bill', [
            'account_id'   => $request->input('account_id'),
            'invoice_date' => $request->input('invoice_date', now()),
            'due_date'     => $request->input('due_date', $purchase_order->due_date),
            'invoice_label' => 'purchase'
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchase_order, $request) {
            $purchase_order->update([
                'invoice_status' => 1, 
                'state'          => 'billed',
                'billed_date'    => $request->input('invoice_date', now()),
                'journal_status' => 1
            ]);

            foreach ($purchase_order->items as $item) {
                $item->update([
                    'invoiced_quantity' => $item->received_quantity
                ]);
            }
        });

        return redirect()->back()->with('success', 'Purchase Bill generated successfully and posted to accounting: ' . $invoice->invoice_number);
    }

    public function deleteBill(PurchaseOrder $purchase_order)
    {
        $this->authorizeModule('edit');
        $this->authorizePlantAccess($purchase_order);

        $hasInward = \App\Models\PurchaseOrderHistory::where('order_id', $purchase_order->id)->exists();
        if ($hasInward || (int)$purchase_order->receipt_status > 0) {
            return redirect()->back()->with('error', 'Purchase Bill cannot be voided as items have already been received for this Purchase Order.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($purchase_order) {
            $bill = $purchase_order->bill;

            foreach ($purchase_order->items as $item) {
                $item->update([
                    'invoiced_quantity' => 0
                ]);
            }

            if ($bill) {
                // Deleting the bill will trigger the Invoice model's deleted hook
                // which handles renaming journal entries (to avoid unique index errors),
                // marking them as deleted, and resetting the PO status.
                $bill->delete();
            } else {
                // Fallback: If the bill relationship was lost but the PO thinks it's billed, 
                // we should still reset the status.
                $purchase_order->update([
                    'invoice_status' => 0,
                    'state'          => 'approved',
                    'journal_status' => 0,
                    'billed_date'    => null
                ]);
            }

            return redirect()->back()->with('success', 'Purchase Bill has been voided and the Purchase Order has been reset.');
        });
    }

    public function destroy($id)
    {
        \Illuminate\Support\Facades\Log::info('Destroy called for PO: ' . $id);
        $purchaseOrder= PurchaseOrder::find($id);
        try {
            $this->authorizeModule('delete');
            \Illuminate\Support\Facades\Log::info('authorizeModule passed');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('authorizeModule failed: ' . $e->getMessage());
            throw $e;
        }

        try {
            $this->authorizePlantAccess($purchaseOrder);
            \Illuminate\Support\Facades\Log::info('authorizePlantAccess passed');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('authorizePlantAccess failed: ' . $e->getMessage());
            throw $e;
        }

        if ((int)$purchaseOrder->receipt_status > 0) {
            return redirect()->back()->with('error', 'Purchase Order cannot be deleted as items have already been received.');
        }

        $purchaseOrder->delete();
        \Illuminate\Support\Facades\Log::info('PO deleted');
        
        return redirect()->back()->with('success', 'Purchase Order deleted successfully.');
    }

    public function downloadPdf(PurchaseOrder $purchaseOrder)
    {
        return redirect()->route('print.document', [
            'module' => 'purchase_orders',
            'id'     => $purchaseOrder->id,
            'action' => 'download'
        ]);
    }

    public function report(PurchaseOrder $purchaseOrder)
    {
        return redirect()->route('print.document', [
            'module' => 'purchase_orders',
            'id'     => $purchaseOrder->id,
            'action' => 'view'
        ]);
    }

   

    protected function authorizePlantAccess(PurchaseOrder $purchaseOrder): void
    {
        abort_unless(in_array((int) $purchaseOrder->plant_id, [session('active_plant_id')], true), 403);
    }
}
