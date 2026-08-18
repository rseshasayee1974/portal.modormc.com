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
            'quotations' => Quotation::with(['patron', 'site', 'items.mixDesign', 'customerPOs', 'creator', 'modifier','salesExecutive'])
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
            'pumpTypeOptions' => PumpTypeDropdown(),
            'pumpRates' => \App\Models\PumpRate::where('status', true)->where('plant_id', $plantId)->get(),
            'instant_customer' => CustomSetting::getForModule(session('active_entity_id'), 'quotation')['instant_customer'] ?? 0,
        ]);
    }

    public function show(Quotation $quotation)
    {
        $this->authorizeModule('menu');

        return Inertia::render('Quotations/Show', [
            'quotation' => $quotation->loadMissing(['patron', 'site', 'items.mixDesign', 'customerPOs', 'creator', 'modifier', 'salesExecutive'])
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
                $firstItem = $quotation->items->first();

                $poData = [
                    'plant_id' => $quotation->plant_id,
                    'patron_id' => $quotation->patron_id,
                    'site_id' => $quotation->site_id,
                    'sales_executive_id' => $quotation->sales_executive_id,
                    'concrete_pump' => $firstItem?->concrete_pump,
                    'is_tax_inclusive' => $quotation->is_tax_inclusive,
                    'pump_rate' => $firstItem?->pump_rate,
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

                // Copy items from quotation to customer PO items
                foreach ($quotation->items as $qItem) {
                    $customerPO->items()->create([
                        'mix_design_id' => $qItem->mix_design_id,
                        'quantity' => $qItem->quantity,
                        'rate' => $qItem->rate,
                        'tax_id' => $qItem->tax_id,
                        'tax_amount' => $qItem->tax_amount,
                        'untaxed_amount' => $qItem->untaxed_amount,
                        'amount_total' => $qItem->amount_total,
                        'concrete_pump' => $qItem->concrete_pump,
                        'pump_rate' => $qItem->pump_rate,
                    ]);
                }
            } else {
                $pos = \App\Models\CustomerPO::where('quotation_id', $quotation->id)->get();
                foreach ($pos as $po) {
                    $po->delete();
                }
            }
        });

        return redirect()->back()->with('success', 'Customer PO conversion status updated.');
    }

    public function sendEmail(Request $request, Quotation $quotation)
    {
        $this->authorizeModule('menu');
        
        \Illuminate\Support\Facades\Log::info("Starting email sending flow for Quotation ID: {$quotation->id}, Reference: {$quotation->reference}");

        $quotation->load(['patron.contacts', 'site']);
        
        $email = $request->input('email');
        if ($email) {
            \Illuminate\Support\Facades\Log::info("Custom email provided in request payload: {$email}");
        }
        
        if (!$email) {
            \Illuminate\Support\Facades\Log::info("No custom email in payload. Resolving primary contact email from patron ID: {$quotation->patron_id}");
            $primaryContact = $quotation->patron->contacts()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->orderByDesc('is_primary')
                ->first();
            
            $email = $primaryContact?->email;
            if ($email) {
                \Illuminate\Support\Facades\Log::info("Resolved contact email: {$email} (Primary: " . ($primaryContact->is_primary ? 'Yes' : 'No') . ", Name: " . ($primaryContact->name ?? 'N/A') . ")");
            } else {
                \Illuminate\Support\Facades\Log::warning("No contact email found for Patron ID: {$quotation->patron_id}");
            }
        }

        if (!$email) {
            return redirect()->back()->with('error', 'Customer does not have a primary contact email.');
        }

        try {
            \Illuminate\Support\Facades\Log::info("Sending QuotationSentNotification synchronously to {$email}...");
            // Send notification synchronously to handle connection/transport errors immediately
            \Illuminate\Support\Facades\Notification::route('mail', $email)
                ->notifyNow(new \App\Notifications\QuotationSentNotification($quotation));

            \Illuminate\Support\Facades\Log::info("Notification sent successfully to {$email}.");

            // Update status if it was draft
            if ((int)$quotation->status === Quotation::STATUS_DRAFT) {
                \Illuminate\Support\Facades\Log::info("Updating quotation status from Draft to Sent.");
                $quotation->update(['status' => Quotation::STATUS_SENT]);
            }

            return redirect()->back()->with('success', "Quotation sent successfully to $email");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send quotation email: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }
    }
}