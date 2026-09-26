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
            ->with(['vendor', 'bill'])
            ->latest()
            ->get();
// dd(VendorsDropdown(['Vendor']));
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
            'bills.items', 'bills.createdBy', 'bill.createdBy',
            'bill.account:id,title'
        ]);

        $this->prepareBillingPreview($purchaseorder);
        return response()->json($purchaseorder);
    }

    public function edit(PurchaseOrder $purchaseorder)
    {
        $this->authorizeModule('edit');

        $this->authorizePlantAccess($purchaseorder);
        $purchaseorder->load(['items.product', 'items.uom', 'items.tax', 'items.history', 'bills.items', 'bill.createdBy', 'bill.account']);
        $this->prepareBillingPreview($purchaseorder);


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
        // $this->authorizeModule('generatebill');
        $this->authorizePlantAccess($purchase_order);

        $this->authorizeModule('edit');
        $request->validate([
            'account_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('mm_ledgers', 'id')->where('plant_id', $purchase_order->plant_id)->whereNull('deleted_at')],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'items' => ['sometimes', 'array'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', \Illuminate\Validation\Rule::exists('mm_purchase_order_items', 'id')->where('order_id', $purchase_order->id)->whereNull('deleted_at')],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $purchase_order) {
            $order = PurchaseOrder::whereKey($purchase_order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array(strtolower($order->state), ['approved', 'billed', 'received']), 422, 'Only approved purchase orders can be billed.');
            $order->load('items.product');
            return $this->generateReceivedBill($request, $order);
        });
    }

    private function generateReceivedBill(Request $request, PurchaseOrder $purchase_order)
    {
        $invoiceData = $this->receivedBillData($request, $purchase_order);
        if (!$invoiceData) {
            return back()->with('error', 'All received quantities have already been billed. Record another receipt before generating a bill.');
        }

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
                    'state'          => (int)$purchase_order->receipt_status === 2 ? 'billed' : 'approved',
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

    private function receivedBillData(Request $request, PurchaseOrder $purchase_order): array
    {
        // Create separate array for loading invoice details from purchase_order
        $itemsData = [];
        $subtotalSum = 0;
        $taxSum = 0;
        $discountSum = 0;
        $receiptOrderValue = 0;
        $billRates = collect($request->input('items', []))->keyBy('order_item_id');
        $converted = $this->conversionBillingEnabled($purchase_order);
        $purchase_order->loadMissing(['items.history', 'bills.items']);
        $quantities = new \App\Services\PurchaseReceiptBilling;

        foreach ($purchase_order->items as $item) {
            if ((float)$item->received_quantity <= (float)$item->invoiced_quantity) {
                continue;
            }

            $billingQuantity = $quantities->quantity($purchase_order, $item, $converted);
            $baseQty = $billingQuantity['received_quantity'];
            $qty = $billingQuantity['quantity'];
            $priceUnit = (float) ($billRates->get($item->id)['unit_price'] ?? $item->unit_price);
            // Allocate fixed PO charges by received share, independently of the bill's rate.
            $receiptOrderValue += (float)$item->product_quantity > 0
                ? (float)$item->price_subtotal * $baseQty / (float)$item->product_quantity : 0;

            // Recalculate discount
            $discountType = $item->discount_type;
            $discountVal = (float) $item->discount_amount;
            $lineSubtotalBeforeDiscount = $qty * $priceUnit;

            if ($discountType === '%') {
                $lineDiscount = ($lineSubtotalBeforeDiscount * $discountVal) / 100;
            } else {
                $orderedQty = (float) $item->product_quantity;
                if ($baseQty == $orderedQty || $orderedQty == 0) {
                    $lineDiscount = $discountVal;
                } else {
                    $lineDiscount = ($discountVal / $orderedQty) * $baseQty;
                }
            }

            $lineSubtotal = $lineSubtotalBeforeDiscount - $lineDiscount;
            if ($lineSubtotal < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'The bill rate must cover the purchase order line discount.',
                ]);
            }

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
                'purchase_order_item_id' => $item->id,
                'item_id'   => $item->product_id,
                'item_name'       => $item->product->title ?? $item->description,
                'hsn_code'        => $item->product->hsn_code ?? null,
                'quantity'        => $qty,
                'uom_id'          => $billingQuantity['uom_id'],
                'price_unit'      => $priceUnit,
                'discount_type'   => $discountType,
                'discount'        => $discountType === '%' ? $discountVal : $lineDiscount,
                'discount_amount' => $lineDiscount,
                'subtotal'        => $lineSubtotal,
                'line_tax_amount' => $lineTax,
                'line_total'      => $lineTotal,
                'tax_id'          => $taxId,
            ];
        }

        if (empty($itemsData)) {
            return [];
        }

        // Allocate order-level charges by value so each receipt bears only its share.
        $orderedValue = (float)$purchase_order->items->sum(fn ($item) => (float)$item->price_subtotal);
        $share = $orderedValue > 0 ? min(1, $receiptOrderValue / $orderedValue) : 0;
        $globalDiscount = round((float)$purchase_order->discount_amount * $share, 2);
        $subtotal = $subtotalSum - $globalDiscount;
        $discountTotal = $discountSum + $globalDiscount;
        $taxAmount = $taxSum;

        $adjustment = round((float)$purchase_order->adjustment * $share, 2);
        $shippingCharges = round((float)$purchase_order->shipping_charges * $share, 2);

        $totalAmount = $subtotal + $taxAmount + $shippingCharges + $adjustment;
        $roundedTotal = round($totalAmount);
        $roundOff = $roundedTotal - $totalAmount;

        return [
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
            'global_discount_type' => '₹',
            'global_discount'  => $globalDiscount,
            'discount_total'   => $discountTotal,
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

    }

    protected function conversionBillingEnabled(PurchaseOrder $order): bool
    {
        return filter_var(CustomSetting::getForModule($order->plant_id, 'batching')['purchase_bill_conversion'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function prepareBillingPreview(PurchaseOrder $order): void
    {
        $converted = $this->conversionBillingEnabled($order);
        $order->setAttribute('conversion_billing_enabled', $converted);
        $order->loadMissing(['items.history.conversionUom', 'bills.items']);
        $quantities = new \App\Services\PurchaseReceiptBilling;
        foreach ($order->items as $item) {
            $item->setAttribute('converted_receipts', $item->history->groupBy('conversion_uom_id')->map(fn ($receipts) => [
                'quantity' => round($receipts->sum('conversion_quantity'), 4),
                'converted_uom' => $receipts->first()->conversionUom?->unit_code,
            ])->values());
            try {
                $preview = $quantities->quantity($order, $item, $converted);
                $preview['converted_uom'] = $converted
                    ? $item->history->firstWhere('conversion_uom_id', $preview['uom_id'])?->conversionUom?->unit_code
                    : $item->uom?->unit_code;
                $item->setAttribute('billing_preview', $preview);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $item->setAttribute('billing_preview', ['error' => $e->validator->errors()->first()]);
            }
        }
    }

    public function deleteBill(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorizeModule('edit');
        $this->authorizePlantAccess($purchase_order);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $purchase_order) {
            PurchaseOrder::whereKey($purchase_order->id)->lockForUpdate()->firstOrFail();
            $bill = $purchase_order->bills()->findOrFail($request->input('bill_id'));
            abort_if((float)$bill->paid_amount > 0 || $bill->paymentAllocations()->exists(), 422, 'Remove payment allocations before voiding this bill.');
            $bill->delete();
            return redirect()->back()->with('success', 'Purchase bill voided. Its received quantity is available to bill again.');
        });
    }

    public function destroy(PurchaseOrder $purchaseorder)
    {
        \Illuminate\Support\Facades\Log::info('Destroy called for PO: ' . $purchaseorder->id);
        $this->authorizeModule('delete');
        $this->authorizePlantAccess($purchaseorder);

        if (!$purchaseorder->canBeDeleted()) {
            if ($purchaseorder->hasInwards()) {
                return redirect()->back()->with('error', 'Purchase Order cannot be deleted as items have already been received or inwarded.');
            }
            if ($purchaseorder->hasBills()) {
                return redirect()->back()->with('error', 'Purchase Order cannot be deleted as a bill has already been generated.');
            }
            return redirect()->back()->with('error', 'Purchase Order cannot be deleted in its current state.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchaseorder) {
            $purchaseorder->delete();
        });

        \Illuminate\Support\Facades\Log::info('PO deleted: ' . $purchaseorder->id);

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
