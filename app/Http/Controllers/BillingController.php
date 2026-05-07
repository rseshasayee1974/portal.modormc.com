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
        
        return Inertia::render('Billing/Index', [
            'invoices' => Invoice::with([
                    'partner:id,legal_name',
                    'account:id,title',
                ])
                ->where('plant_id', $plantId)
                ->where('invoice_type', 'bill')
                ->latest()
                ->get(),
            'patrons' => toSelectOptions(PatronsDropdown($plantId), 'legal_name'),
            'taxes'   => collect(TaxesDropdown($plantId, 'purchase'))->map(fn($t) => [
                'label' => $t->tax_name,
                'value' => $t->id,
                'rate'  => $t->tax_rate,
            ]),
            'accounts' => toSelectOptions(LedgersDropdown($plantId, 'EXPENSE'), 'title'),
            'products'       =>ProductsDropdown($plantId, 'purchase'),
            'units'    => toSelectOptions(Productunit(), 'unit_code'),
            'instant_invoice_patron' => CustomSetting::getForModule(session('active_entity_id'), 'billing')['instant_invoice_patron'] ?? 0,
            'next_invoice_number' => Invoice::generateNumber($plantId, 'bill'),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');

        $invoice = Invoice::createWithItems(array_merge($request->validated(), [
            'plant_id'     => $plantId,
            'invoice_type' => 'bill',
            'status'       => Invoice::STATUS_PAID,
            'created_by'   => Auth::id(),
        ]));

        return redirect()->back()->with('success', 'Bill created successfully as ' . $invoice->invoice_number);
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
                'createdBy:id,username'
            ])
        );
    }

    public function destroy(Invoice $billing)
    {
        $invoice = $billing;
        $this->authorizeModule('delete');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($invoice) {
            // Soft delete the invoice. 
            // The Invoice model's 'deleted' hook will handle:
            // 1. Reversing PO billed status
            // 2. Voiding associated Journal Entries
            $invoice->delete();

            return redirect()->back()->with('success', 'Bill voided successfully and associated Purchase Order has been reset.');
        });
    }
}
