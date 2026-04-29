<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tax;
use App\Models\Accounts;
use App\Models\Machine;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\CustomSetting;

class InvoiceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'invoices';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('Invoices/Index', [
            'invoices' => Invoice::with([
                    'partner:id,legal_name',
                    'items',
                    'orderTaxes',
                    'account',
                    'truck',
                ])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'patrons' => toSelectOptions(PatronsDropdown($plantId), 'legal_name'),
            'taxes'   => toSelectOptions(TaxesDropdown($plantId), 'tax_name'),
            'trucks'  => toSelectOptions(VehiclesDropdown($plantId), 'registration'),
            'accounts' => toSelectOptions(LedgersDropdown(), 'title'), // Using Ledgers as accounts for now or correct helper
            'instant_invoice_patron' => CustomSetting::getForModule(session('active_entity_id'), 'invoice')['instant_invoice_patron'] ?? 0,
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');

        $invoice = Invoice::createWithItems(array_merge($request->validated(), [
            'plant_id'   => $plantId,
            'created_by' => Auth::id(),
        ]));

        return redirect()->back()->with('success', 'Invoice created successfully as ' . $invoice->invoice_number);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        if ($invoice->status !== Invoice::STATUS_DRAFT && !$request->has('status')) {
            return redirect()->back()->withErrors(['error' => 'Only draft invoices can be edited.']);
        }

        $invoice->updateWithItems(array_merge($request->validated(), [
            'updated_by' => Auth::id(),
        ]));

        return redirect()->back()->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorizeModule('delete');

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_APPROVED])) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete an approved or paid invoice.']);
        }

        $invoice->delete();
        return redirect()->back()->with('success', 'Invoice voided.');
    }
}
