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
            ->with(['vendor', 'currency'])
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

    public function show(PurchaseOrder $purchaseorder)
    {
        $this->authorizeModule('view');
        $this->authorizePlantAccess($purchaseorder);

        $purchaseorder->load([
            'items.product', 
            'items.uom', 
            'items.tax', 
            'items.history', 
            'bill.createdBy', 
            'bill.account:id,title'
        ]);

        return response()->json($purchaseorder);
    }
 
    public function edit(PurchaseOrder $purchaseorder)
    {
        $this->authorizeModule('edit');

        $this->authorizePlantAccess($purchaseorder);
        $purchaseorder->load(['items.product', 'items.uom', 'items.tax', 'items.history', 'bill.createdBy', 'bill.account']);
 

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseorder,
            'vendors'       => VendorsDropdown(['Vendor']),
            'vehicles'      => VehiclesDropdown(),
            'currencies'    => CurrenciesDropdown(),
            'taxes'         => TaxesDropdown('purchase', ['GST', 'IGST']),
            'products'      => ProductsDropdown('purchase'),
            'productUnits'  => Productunit('purchase'),
            'accounts'      => toSelectOptions(LedgersDropdown('EXPENSE'), 'title'),
            'ref_no'        => Financialyear::generatePurchaseOrderRefNo($purchaseorder->plant_id, $purchaseorder->date_order?->toDateString()),
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
        $existingBill = \App\Models\Invoice::where('ref_id','=', $purchase_order->id)
            ->where('invoice_type', 'bill')->where('invoice_label','=','purchase')
            ->where('plant_id', session('active_plant_id', $purchase_order->plant_id))
            ->whereNull('deleted_at')
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

        // Create separate array for loading invoice details from purchase_order
        $itemsData = [];
        $subtotalSum = 0;
        $taxSum = 0;
        $discountSum = 0;
        
        foreach ($purchase_order->items as $item) {
            if ((float)$item->received_quantity <= 0) {
                continue;
            }

            $qty = (float) ($item->invoiced_quantity > 0 ? $item->invoiced_quantity : $item->received_quantity);
            $priceUnit = (float) $item->unit_price;
            
            // Recalculate discount
            $discountType = $item->discount_type;
            $discountVal = (float) $item->discount_amount;
            $lineSubtotalBeforeDiscount = $qty * $priceUnit;
            
            if ($discountType === 'percentage') {
                $lineDiscount = ($lineSubtotalBeforeDiscount * $discountVal) / 100;
            } else {
                $orderedQty = (float) $item->product_quantity;
                if ($qty == $orderedQty || $orderedQty == 0) {
                    $lineDiscount = $discountVal;
                } else {
                    $lineDiscount = ($discountVal / $orderedQty) * $qty;
                }
            }
            
            $lineSubtotal = $lineSubtotalBeforeDiscount - $lineDiscount;
            
            // Recalculate tax
            $taxRate = 0;
            $taxId = $item->tax_id;
            if ($taxId) {
                $tax = \App\Models\Tax::where('id', $taxId)
                    ->where('plant_id', session('active_plant_id', $purchase_order->plant_id))
                    ->whereNull('deleted_at')
                    ->first();
                if ($tax) {
                    $taxRate = (float) $tax->tax_rate;
                }
            }
            
            $lineTax = ($lineSubtotal * $taxRate) / 100;
            $lineTotal = $lineSubtotal + $lineTax;
            
            $subtotalSum += $lineSubtotal;
            $taxSum += $lineTax;
            $discountSum += $lineDiscount;
            
            $itemsData[] = [
                'item_id'   => $item->product_id,
                'item_name'       => $item->product->title ?? $item->description,
                'hsn_code'        => $item->product->hsn_code ?? null,
                'quantity'        => $qty,
                'uom_id'          => $item->product_uom ?? $item->uom_id,
                'price_unit'      => $priceUnit,
                'discount_type'   => $discountType,
                'discount'        => $item->discount_amount,
                'discount_amount' => $lineDiscount,
                'subtotal'        => $lineSubtotal,
                'line_tax_amount' => $lineTax,
                'line_total'      => $lineTotal,
                'tax_id'          => $taxId,
            ];
        }

        if (empty($itemsData)) {
            return redirect()->back()->with('error', 'Cannot generate bill: No items have a received quantity greater than 0.');
        }

        $subtotal = $subtotalSum - (float)$purchase_order->discount_amount;
        $discountTotal = $discountSum + (float)$purchase_order->discount_amount;
        $taxAmount = $taxSum;
        
        $adjustment = (float)$purchase_order->adjustment;
        $shippingCharges = (float)$purchase_order->shipping_charges;
        
        $totalAmount = $subtotal + $taxAmount + $shippingCharges + $adjustment;
        $roundedTotal = round($totalAmount);
        $roundOff = $roundedTotal - $totalAmount;

        $invoiceData = [
            'plant_id'         => session('active_plant_id', $purchase_order->plant_id),
            'partner_id'       => $purchase_order->vendor_id,
            'account_id'       => $request->input('account_id'),
            'invoice_type'     => 'bill',
            'invoice_label'    => 'purchase',
            'ref_id'           => $purchase_order->id,
            'ref_title'        => $purchase_order->po_number ?? $purchase_order->ref_no,
            'invoice_date'     => $request->input('invoice_date', now()),
            'due_date'         => $request->input('due_date', $purchase_order->due_date),
            'subtotal'         => $subtotal,
            'global_discount'  => $discountTotal,
            'tax_amount'       => $taxAmount,
            'adjustment'       => $adjustment,
            'shipping_charges' => $shippingCharges,
            'round_off'        => $roundOff,
            'total_amount'     => $roundedTotal,
            'balance_amount'   => $roundedTotal,
            'status'           => \App\Models\Invoice::STATUS_APPROVED,
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
            'items'            => $itemsData
        ];

        try {
            $invoice = null;
            \Illuminate\Support\Facades\DB::transaction(function () use ($purchase_order, $request, $invoiceData, &$invoice) {
                $invoice = \App\Models\Invoice::createWithItems($invoiceData);

                if ($invoice->status === \App\Models\Invoice::STATUS_APPROVED || $invoice->status === \App\Models\Invoice::STATUS_PAID) {
                    $invoice->postToAccounting();
                }

                $purchase_order->update([
                    'invoice_status' => 1, 
                    'billing_id' => $invoice->id,
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
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error generating bill: ' . $e->getMessage());
        }
    }

    public function deleteBill(PurchaseOrder $purchase_order)
    {
        $this->authorizeModule('edit');
        $this->authorizePlantAccess($purchase_order);

        // $hasInward = \App\Models\PurchaseOrderHistory::where('order_id', $purchase_order->id)->exists();
        // if ($hasInward || (int)$purchase_order->receipt_status > 0) {
        //     return redirect()->back()->with('error', 'Purchase Bill cannot be voided as items have already been received for this Purchase Order.');
        // }

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
                // marking them as deleted.
                $bill->delete();
            }

            // Always reset the PO status manually to guarantee data integrity
            $newState = ($purchase_order->receipt_status > 0) ? 'received' : 'approved';
            
            $purchase_order->update([
                'invoice_status' => 0,
                'billing_id'     => null,
                'state'          => $newState,
                'journal_status' => 0,
                'billed_date'    => null
            ]);

            return redirect()->back()->with('success', 'Purchase Bill has been voided and the Purchase Order has been reset.');
        });
    }

    public function destroy(PurchaseOrder $purchaseorder)
    {
        \Illuminate\Support\Facades\Log::info('Destroy called for PO: ' . $purchaseorder->id);
        $this->authorizeModule('delete');
        $this->authorizePlantAccess($purchaseorder);

        if ((int)$purchaseorder->receipt_status > 0) {
            return redirect()->back()->with('error', 'Purchase Order cannot be deleted as items have already been received.');
        }

        $purchaseorder->delete();
        \Illuminate\Support\Facades\Log::info('PO deleted');
        
        return redirect()->back()->with('success', 'Purchase Order deleted successfully.');
    }

    public function downloadPdf(PurchaseOrder $purchase_order)
    {
        return redirect()->route('print.document', [
            'module' => 'purchase_orders',
            'id'     => $purchase_order->id,
            'action' => 'download'
        ]);
    }

    public function report(PurchaseOrder $purchase_order)
    {
        return redirect()->route('print.document', [
            'module' => 'purchase_orders',
            'id'     => $purchase_order->id,
            'action' => 'view'
        ]);
    }

   

    protected function authorizePlantAccess(PurchaseOrder $purchaseOrder): void
    {
        abort_unless((int) $purchaseOrder->plant_id === (int) session('active_plant_id'), 403);
    }
}