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
            
            'accounts'      => toSelectOptions(LedgersDropdown(session('active_plant_id'), 'EXPENSE'), 'title'),
            'vendors'        => VendorsDropdown($allowedPlantIds, ['Vendor']),
            'vehicles'       => VehiclesDropdown($allowedPlantIds),
            'currencies'     => CurrenciesDropdown(),
            'taxes'          => TaxesDropdown($allowedPlantIds, 'purchase', ['GST', 'IGST']),
            'products'       => ProductsDropdown($allowedPlantIds, 'purchase'),
            'productUnits'   => Productunit(),
            'instant_vendor' => CustomSetting::getForModule(session('active_plant_id'), 'purchase')['instant_vendor'] ?? 0,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeModule('create');

        $allowedPlantIds = $this->allowedPlantIds();
        $plantId = session('active_plant_id');

        return Inertia::render('PurchaseOrders/Create', [
            'vendors'      => VendorsDropdown($allowedPlantIds, ['Vendor']),
            'vehicles'     => VehiclesDropdown($allowedPlantIds),
             
            'taxes'        => TaxesDropdown($allowedPlantIds, 'purchase', ['GST', 'IGST']),
            'products'     => ProductsDropdown($allowedPlantIds, 'purchase'),
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

        $allowedPlantIds = $this->allowedPlantIds();

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'vendors'       => VendorsDropdown($allowedPlantIds, ['Vendor']),
            'vehicles'      => VehiclesDropdown($allowedPlantIds),
            'currencies'    => CurrenciesDropdown(),
            'taxes'         => TaxesDropdown($allowedPlantIds, 'purchase', ['GST', 'IGST']),
            'products'      => ProductsDropdown($allowedPlantIds, 'purchase'),
            'productUnits'  => Productunit('purchase'),
            'accounts'      => toSelectOptions(LedgersDropdown(session('active_plant_id'), 'EXPENSE'), 'title'),
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

        // Optional: Check if already invoiced
        if ($purchase_order->invoice_status === 'invoiced') {
            return redirect()->back()->with('error', 'A bill has already been generated for this Purchase Order.');
        }

        // Use the common invoice generation function
        $invoice = \App\Http\Controllers\InvoiceController::createFromSource($purchase_order, 'bill', [
            'account_id'   => $request->input('account_id'),
            'invoice_date' => $request->input('invoice_date', now()),
            'due_date'     => $request->input('due_date', $purchase_order->due_date),
        ]);

        $purchase_order->update(['invoice_status' => 'invoiced', 'state' => 'billed']);

        return redirect()->back()->with('success', 'Purchase Bill generated successfully: ' . $invoice->invoice_number);
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

    public function allowedPlantIds(): array
    {
        $plantIds = Auth::user()
            ->entityUsers()
            ->pluck('plant_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->toArray();

        $activePlantId = (int) session('active_plant_id');
        if ($activePlantId > 0 && !in_array($activePlantId, $plantIds, true)) {
            $plantIds[] = $activePlantId;
        }

        return $plantIds;
    }

    protected function authorizePlantAccess(PurchaseOrder $purchaseOrder): void
    {
        abort_unless(in_array((int) $purchaseOrder->plant_id, [session('active_plant_id')], true), 403);
    }
}
