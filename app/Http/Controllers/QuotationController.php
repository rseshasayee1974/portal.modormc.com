<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use App\Models\CustomSetting;

class QuotationController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'quotations';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('Quotations/Index', [
            'quotations' => Quotation::with(['patron', 'site', 'items.mixDesign', 'salesOrders', 'creator', 'modifier', 'salesExecutive'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'patrons'  => PatronsDropdown(['Customer']),
            'sites'    => SitesDropdown(),
            'mixDesigns' => MixDesignsDropdown(),
            'taxes'    => TaxesDropdown('Sales', ['GST', 'IGST']),
            'vehicles' => MachinesDropdown(),
            'drivers'  => PersonnelDropdown(),
            'salesExecutives' => SalesExecutivesDropdown(),
            'unitOptions' => Productunit(),
            'instant_customer' => CustomSetting::getForModule(session('active_entity_id'), 'quotation')['instant_customer'] ?? 0,
        ]);
    }

    public function store(StoreQuotationRequest $request)
    {
        $this->authorizeModule('create');
        
        Quotation::createWithItems($request->validated(), session('active_plant_id'));

        return redirect()->back()->with('success', 'Quotation drafted successfully.');
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation)
    {
        $this->authorizeModule('edit');

        if (in_array((int)$quotation->status, [Quotation::STATUS_ACCEPTED, Quotation::STATUS_REJECTED])) {
            return redirect()->back()->with('error', 'Finalized quotations cannot be modified.');
        }
        
        $quotation->updateWithItems($request->validated());

        return redirect()->back()->with('success', 'Quotation updated successfully.');
    }

    public function destroy(Quotation $quotation)
    {
        $this->authorizeModule('delete');
        
        if (in_array((int)$quotation->status, [Quotation::STATUS_ACCEPTED, Quotation::STATUS_REJECTED])) {
            return redirect()->back()->with('error', 'Finalized quotations cannot be deleted.');
        }

        $quotation->delete();
        return redirect()->back()->with('success', 'Quotation voided.');
    }

    public function downloadPdf(Quotation $quotation)
    {
        return redirect()->route('print.document', [
            'module' => 'quotations',
            'id'     => $quotation->id,
            'action' => 'download'
        ]);
    }

    public function report(Quotation $quotation)
    {
        return redirect()->route('print.document', [
            'module' => 'quotations',
            'id'     => $quotation->id,
            'action' => 'view'
        ]);
    }

    public function updateConversionStatus(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'is_salesorder' => 'required|integer|in:0,1,-1'
        ]);

        $quotation->update($validated);

        $isSalesOrder = (int) $validated['is_salesorder'];
        if ($isSalesOrder === 1) {
            $user = auth()->user();

            \App\Models\SalesOrder::updateOrCreate(
                ['quotation_id' => $quotation->id],
                [
                    'plant_id' => $quotation->plant_id,
                    'patron_id' => $quotation->patron_id,
                    'site_id' => $quotation->site_id,
                    'sales_executive_id' => $quotation->sales_executive_id,
                    'order_date' => now()->toDateString(),
                    'status' => \App\Models\SalesOrder::STATUS_CONFIRMED,
                    'converted_by_user_id' => $user->id,
                ]
            );
        } else {
            \App\Models\SalesOrder::where('quotation_id', $quotation->id)->delete();
        }

        return redirect()->back()->with('success', 'Sales Order conversion status updated.');
    }

    public function sendEmail(Quotation $quotation)
    {
        $this->authorizeModule('menu');
        
        $quotation->load(['patron.contacts', 'site']);
        
        $primaryContact = $quotation->patron->contacts()->where('is_primary', 1)->first() 
                        ?? $quotation->patron->contacts()->first();
        
        $email = $primaryContact?->email;

        if (!$email) {
            return redirect()->back()->with('error', 'Customer does not have a primary contact email.');
        }

        // Send notification
        \Illuminate\Support\Facades\Notification::route('mail', $email)
            ->notify(new \App\Notifications\QuotationSentNotification($quotation));

        // Update status if it was draft
        if ((int)$quotation->status === Quotation::STATUS_DRAFT) {
            $quotation->update(['status' => Quotation::STATUS_SENT]);
        }

        return redirect()->back()->with('success', "Quotation sent successfully to $email");
    }
}
