<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tax;
use App\Models\Accounts;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\CustomSetting;

class BillingController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'billings';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');
        $invoice=Invoice::with([
                    'partner:id,legal_name',
                    'account:id,title',
                    'destroyer:id,username',
                    'items',
                    'items.uom:id,unit_code',
                    'items.tax',
                ])
                ->where('plant_id', $plantId)
                ->where('invoice_type', 'bill')
                ->latest()
                ->get();
                // dd($invoice);
        return Inertia::render('Billing/Index', [
            'invoices' => Invoice::with([
                    'partner:id,legal_name',
                    'account:id,title',
                    'destroyer:id,username',
                    'items',
                    'items.uom:id,unit_code',
                    'items.tax',
                ])
                ->where('plant_id', $plantId)
                ->where('invoice_type', 'bill')
                ->latest()
                ->get(),
            'patrons' => toSelectOptions(PatronsDropdown(), 'legal_name'),
            'taxes'   => collect(TaxesDropdown('purchase'))->map(fn($t) => [
                'label' => $t->tax_name,
                'value' => $t->id,
                'rate'  => $t->tax_rate,
            ]),
            'accounts' => toSelectOptions(LedgersDropdown('EXPENSE'), 'title'),
            'products'       => ProductsDropdown('purchase'),
            'units'    => toSelectOptions(Productunit(), 'unit_code'),
            'instant_invoice_patron' => CustomSetting::getForModule(session('active_entity_id'), 'billing')['instant_invoice_patron'] ?? 0,
            'next_invoice_number' => Invoice::generateNumber($plantId, 'bill')['full_number'],
            'next_invoice_details' => Invoice::generateNumber($plantId, 'bill'),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $plantId) {
            $validated = $request->validated();
// dd($validated );
            // Auto-generate numbering if not provided
            if (empty($validated['invoice_number'])) {
                $details = Invoice::generateNumber($plantId, 'bill');
                $validated['prefix'] = $details['prefix'];
                $validated['invoice_number'] = $details['next_number'];
            }

            $invoice = Invoice::createWithItems(array_merge($validated, [
                'plant_id'        => $plantId,
                'ref_id'          => implode(',',$validated['purchase_order_ids']) ?? null,
                'invoice_type'    => 'bill',
                'status'          => Invoice::STATUS_APPROVED,
                'due_date'        => $validated['due_date'] ?? $validated['invoice_date'],
                'einvoice_status' => 0,
                'created_by'      => Auth::id(),
            ]));

            // If PO-wise billing, update the purchase orders
            if ($request->has('purchase_order_ids') && is_array($request->purchase_order_ids)) {
                \App\Models\PurchaseOrder::whereIn('id', $request->purchase_order_ids)->update([
                    'billing_id'     => $invoice->id,
                    'billing_status' => 'Billed',
                    'invoice_status' => 1, // Legacy status field if exists
                    'journal_status' => '1',
                    'billed_date'    => $invoice->invoice_date,
                ]);
            }

            $invoice->postToAccounting();

            return redirect()->back()->with('success', 'Bill created successfully as ' . $invoice->invoice_number);
        });
    }

    public function getUnbilledPurchaseOrders(Request $request)
    {
        $this->authorizeModule('menu');
        
        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $startDate = \Illuminate\Support\Carbon::parse($validated['start_date'])->startOfDay();
        $endDate   = \Illuminate\Support\Carbon::parse($validated['end_date'])->endOfDay();

        $pos = \App\Models\PurchaseOrder::with(['items.product', 'items.uom'])
            ->where('vendor_id', $validated['partner_id'])
            ->whereBetween('date_order', [$startDate, $endDate])
            ->where('approve_status', 'Approved') // Only bill approved POs
            ->whereNull('billing_id')
            ->where('plant_id', session('active_plant_id'))
            ->latest()
            ->get();

        return response()->json($pos);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $billing)
    {
        $invoice = $billing;
        $this->authorizeModule('edit');

        if ($invoice->status !== Invoice::STATUS_DRAFT && !$request->has('status')) {
            return redirect()->back()->withErrors(['error' => 'Only draft bills can be edited.']);
        }

        $invoice->updateWithItems(array_merge($request->validated(), [
            'updated_by' => Auth::id(),
        ]));

        return redirect()->back()->with('success', 'Bill updated successfully.');
    }

    public function show(Invoice $billing)
    {
        $invoice = $billing;
        $this->authorizeModule('view');
 
        return response()->json(
            $invoice->load([
                'partner:id,legal_name',
                'items.tax',
                'items.uom:id,unit_code',
                'orderTaxes',
                'account:id,title',
                'createdBy:id,username',
                'destroyer:id,username',
            ])
        );
              
    }

    public function destroy(Invoice $billing)
    {
        $invoice = $billing;
        $this->authorizeModule('delete');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($invoice) {
            // Explicitly reverse PO statuses before deletion
            if ($invoice->ref_id) {
                \App\Models\PurchaseOrder::whereIn('id', explode(",", $invoice->ref_id))->update([
                    'billing_id'     => null,
                    'billing_status' => 'Pending',
                    'invoice_status' => 0, 
                    'journal_status' => '0',
                    'billed_date'    => null,
                ]);
            }

            // Soft delete the invoice. 
            // The Invoice model's 'deleted' hook also handles cleanup tasks.
            $invoice->delete();

            return redirect()->back()->with('success', 'Bill voided successfully and associated Purchase Order has been reset.');
        });
    }
}
