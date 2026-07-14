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
            'quotations' => Quotation::with(['patron', 'site', 'items.mixDesign', 'customerPOs', 'creator', 'modifier','salesExecutive', 'concretePump'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'patrons'  => PatronsDropdown(['Customer']),
            'salesExecutives' => SalesExecutivesDropdown(),
            'sites'    => SitesDropdown(),
            'mixDesigns' => MixDesignsDropdown(),
            'taxes'    => TaxesDropdown('Sales', ['GST', 'IGST']),
            'vehicles' => MachinesDropdown(),
            'drivers'  => PersonnelDropdown(),
            'unitOptions' => Productunit(),
            'concretePumpOptions' => ConcretePumpDropdown(),
            'instant_customer' => CustomSetting::getForModule(session('active_entity_id'), 'quotation')['instant_customer'] ?? 0,
        ]);
    }

    public function show(Quotation $quotation)
    {
        $this->authorizeModule('menu');

        return Inertia::render('Quotations/Show', [
            'quotation' => $quotation->loadMissing(['patron', 'site', 'items.mixDesign', 'customerPOs', 'creator', 'modifier', 'salesExecutive', 'concretePump'])
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
            'id'     => encrypt($quotation->id),
            'action' => 'download'
        ]);
    }

    public function report(Quotation $quotation)
    {
        return redirect()->route('print.document', [
            'module' => 'quotations',
            'id'     => encrypt($quotation->id),
            'action' => 'view'
        ]);
    }

    public function updateConversionStatus(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'is_customer_po' => 'required|integer|in:0,1,-1'
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $quotation->update($validated);

            $isSalesOrder = (int) $validated['is_customer_po'];
            if ($isSalesOrder === 1) {
                $user = auth()->user();

                $existingPO = \App\Models\CustomerPO::where('quotation_id', $quotation->id)->first();
                $poData = [
                    'plant_id' => $quotation->plant_id,
                    'patron_id' => $quotation->patron_id,
                    'site_id' => $quotation->site_id,
                    'sales_executive_id' => $quotation->sales_executive_id,
                    'concrete_pump' => $quotation->concrete_pump,
                    'pump_rate' => $quotation->pump_rate,
                    'manual_rate' => $quotation->manual_rate,
                    'boom_pump_rate' => $quotation->boom_pump_rate,
                    'order_date' => $existingPO ? $existingPO->order_date : now()->toDateString(),
                    'status' => \App\Models\CustomerPO::STATUS_CONFIRMED,
                    'converted_by_user_id' => $user->id,
                ];

                if (!$existingPO) {
                    $details = \App\Models\CustomerPO::generateReference($quotation->plant_id);
                    $poData['prefix'] = $details['prefix'];
                    $poData['reference'] = $details['reference'];
                }

                $customerPO = \App\Models\CustomerPO::updateOrCreate(
                    ['quotation_id' => $quotation->id],
                    $poData
                );

                // Clear any existing items in the customer PO to avoid duplicates/orphans
                $customerPO->items()->delete();

                // Copy items from quotation to customer PO items via bulk insert
                $quotation->loadMissing('items');
                $itemsData = $quotation->items->map(function ($item) {
                    return [
                        'mix_design_id' => $item->mix_design_id,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'tax_id' => $item->tax_id,
                        'tax_amount' => $item->tax_amount,
                        'untaxed_amount' => $item->untaxed_amount,
                        'amount_total' => $item->amount_total,
                    ];
                })->toArray();

                $customerPO->items()->createMany($itemsData);
            } else {
                \App\Models\CustomerPO::where('quotation_id', $quotation->id)->delete();
            }
        });

        return redirect()->back()->with('success', 'Customer PO conversion status updated.');
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