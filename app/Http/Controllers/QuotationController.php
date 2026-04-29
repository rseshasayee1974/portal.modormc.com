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
            'quotations' => Quotation::with(['patron', 'site', 'items.mixDesign', 'salesOrders', 'creator', 'modifier'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'patrons'  => PatronsDropdown($plantId,['Customer']),
            'sites'    => SitesDropdown($plantId),
            'mixDesigns' => MixDesignsDropdown($plantId),
            'taxes'    => TaxesDropdown($plantId, 'Sales', ['GST', 'IGST']),
            'vehicles' => MachinesDropdown($plantId),
            'drivers'  => PersonnelDropdown($plantId),
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

        return redirect()->back()->with('success', 'Sales Order conversion status updated.');
    }
}
