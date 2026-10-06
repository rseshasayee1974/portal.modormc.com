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
        $this->authorizePlantAccess($purchase_order);
        $this->authorizeModule('edit');

        try {
            $invoice = app(\App\Services\PurchaseBillGenerator::class)->generate($request, $purchase_order);
            if (!$invoice) {
                return back()->with('error', 'All received quantities have already been billed. Record another receipt before generating a bill.');
            }

            return back()->with('success', 'Purchase Bill generated successfully and posted to accounting: ' . $invoice->invoice_number);
        } catch (\Illuminate\Validation\ValidationException | \Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Error generating bill: ' . $e->getMessage());
        }
    }

    private function receivedBillData(Request $request, PurchaseOrder $purchase_order): array
    {
        return app(\App\Services\PurchaseBillGenerator::class)->buildData($request, $purchase_order);
    }

    private function prepareBillingPreview(PurchaseOrder $order): void
    {
        $order->setAttribute('conversion_billing_enabled', false);
        $order->loadMissing(['items.history.conversionUom', 'bills.items']);
        $quantities = new \App\Services\PurchaseReceiptBilling;
        foreach ($order->items as $item) {
            $inwards = [];
            foreach ($item->history as $receipt) {
                try {
                    $preview = $quantities->quantity($order, $item, false, $receipt->id);
                    if ($preview['received_quantity'] <= 0) continue;
                    $preview['converted_uom'] = $item->uom?->unit_code;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $preview = ['error' => $e->validator->errors()->first()];
                }
                $inwards[] = ['id' => $receipt->id, 'inward_no' => $receipt->inward_no,
                    'received_date' => $receipt->received_date, 'billing_preview' => $preview];
            }
            $item->setAttribute('billable_inwards', $inwards);
            $item->setAttribute('converted_receipts', $item->history->groupBy('conversion_uom_id')->map(fn ($receipts) => [
                'quantity' => round($receipts->sum('conversion_quantity'), 4),
                'converted_uom' => $receipts->first()->conversionUom?->unit_code,
            ])->values());
            try {
                $preview = $quantities->quantity($order, $item, false);
                $preview['converted_uom'] = $item->uom?->unit_code;
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
