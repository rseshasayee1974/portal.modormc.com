<?php

namespace App\Http\Controllers;

use App\Models\CustomerPO;
use App\Models\Quotation;
use App\Models\SalesOrder; // This is the new SalesOrder model (formerly WorkOrder)
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerPOController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'customer_pos';


    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $customerPOs = CustomerPO::with(['patron', 'site', 'items.mixDesign', 'quotation.items.mixDesign', 'converter:username,email,id,is_active,mobile', 'salesOrders', 'salesExecutive'])
            ->where('plant_id', $plantId)
            ->latest()
            ->get();

        $sites = SitesDropdown();
        $quotations = Quotation::with(['items.mixDesign'])
            ->select('id', 'reference', 'amount_total', 'patron_id', 'site_id', 'is_customer_po')
            ->where('plant_id', $plantId)
            ->where('is_customer_po', 0)
            ->orderBy('reference')
            ->get();
        $mixDesigns = MixDesignsDropdown();

        return Inertia::render('CustomerPOs/Index', [
            'customerPOs' => $customerPOs, // keep prop name 'salesOrders' for template property compatibility or change to customerPOs if we rename it. Let's keep salesOrders so we don't break frontend props too much
            'patrons' =>  PatronsDropdown(['Customer']),
            'sites' => $sites,
            'quotations' => $quotations,
            'mixDesigns' => $mixDesigns,
            'salesExecutives' => SalesExecutivesDropdown(),
            'concretePumpOptions' => ConcretePumpDropdown(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'prefix' => 'nullable|string|max:20',
            'reference' => 'nullable|string|max:100',
            'quotation_id' => 'nullable|exists:mm_quotations,id',
            'patron_id' => 'required|exists:mm_patrons,id',
            'site_id' => 'required|exists:mm_sites,id',
            'sales_executive_id' => 'nullable|exists:mm_personnels,id',
            'concrete_pump' => 'nullable|string',
            'order_date' => 'required|date',
            'mix_design_id' => 'nullable|exists:mm_mix_designs,id',
            'quantity' => 'nullable|numeric|min:0.001',
            'rate' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.mix_design_id' => 'required_without:quotation_id|exists:mm_mix_designs,id',
            'items.*.quantity' => 'required_without:quotation_id|numeric|min:0.001',
            'items.*.rate' => 'required_without:quotation_id|numeric|min:0',
        ]);

        $formattedDate = \Carbon\Carbon::parse($validated['order_date'])->format('Y-m-d');
        $validated['order_date'] = $formattedDate;

        $plantId = session('active_plant_id') ?: 1;
        $validated['plant_id'] = $plantId;
        
        $validated['status'] = empty($validated['quotation_id']) 
            ? CustomerPO::STATUS_DRAFT 
            : CustomerPO::STATUS_CONFIRMED;

        $user = auth()->user();
        $validated['converted_by_user_id'] = $user->id;

        if (empty($validated['reference'])) {
            $details = CustomerPO::generateReference($plantId, $validated['prefix'] ?? null);
            $validated['prefix'] = $details['prefix'];
            $validated['reference'] = $details['reference'];
        } else {
            if (empty($validated['prefix'])) {
                $validated['prefix'] = 'CPO';
            }
        }

        $items = [];
        if (!empty($validated['items'])) {
            $items = $validated['items'];
        } elseif (!empty($validated['mix_design_id'])) {
            $items = [[
                'mix_design_id' => $validated['mix_design_id'],
                'quantity' => $validated['quantity'],
                'rate' => $validated['rate'],
            ]];
        }

        if (empty($validated['quotation_id']) && empty($items)) {
            return redirect()->back()->withErrors(['items' => 'At least one mix design item is required.']);
        }

        DB::transaction(function () use ($validated, $plantId, $items) {
            $quote = null;
            if (!empty($validated['quotation_id'])) {
                $quote = Quotation::find($validated['quotation_id']);
                Quotation::where('id', $validated['quotation_id'])->update([
                    'status' => Quotation::STATUS_ACCEPTED,
                    'is_customer_po' => 1
                ]);
            }

            $customerPO = CustomerPO::create([
                'plant_id' => $plantId,
                'prefix' => $validated['prefix'],
                'reference' => $validated['reference'],
                'quotation_id' => $validated['quotation_id'] ?: null,
                'patron_id' => $validated['patron_id'],
                'site_id' => $validated['site_id'],
                'sales_executive_id' => $validated['sales_executive_id'] ?? ($quote?->sales_executive_id ?? null),
                'concrete_pump' => $validated['concrete_pump'] ?? ($quote?->concrete_pump ?? null),
                'order_date' => $validated['order_date'],
                'status' => $validated['status'],
                'converted_by_user_id' => $validated['converted_by_user_id'],
            ]);

            if (empty($validated['quotation_id'])) {
                foreach ($items as $item) {
                    $customerPO->items()->create([
                        'mix_design_id' => $item['mix_design_id'],
                        'quantity' => $item['quantity'],
                        'rate' => $item['rate'],
                        'tax_id' => null,
                        'tax_amount' => 0,
                        'untaxed_amount' => $item['quantity'] * $item['rate'],
                        'amount_total' => $item['quantity'] * $item['rate'],
                    ]);
                }
            } else {
                $customerPO->load('quotation.items');
                foreach ($customerPO->quotation->items as $item) {
                    $customerPO->items()->create([
                        'mix_design_id' => $item->mix_design_id,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'tax_id' => $item->tax_id,
                        'tax_amount' => $item->tax_amount,
                        'untaxed_amount' => $item->untaxed_amount,
                        'amount_total' => $item->amount_total,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Customer PO created successfully.');
    }

    public function destroy(CustomerPO $customerPO)
    {
        $this->authorizeModule('delete');
        
        if ($customerPO->quotation_id) {
            Quotation::where('id', $customerPO->quotation_id)->update([
                'is_customer_po' => 0
            ]);
        }

        $customerPO->delete();

        return redirect()->back()->with('success', 'Customer PO deleted.');
    }

    public function update(Request $request, CustomerPO $customerPO)
    {
        $this->authorizeModule('update');
        
        $validated = $request->validate([
            'prefix' => 'nullable|string|max:20',
            'reference' => 'nullable|string|max:100',
            'quotation_id' => 'nullable|exists:mm_quotations,id',
            'patron_id' => 'required|exists:mm_patrons,id',
            'site_id' => 'required|exists:mm_sites,id',
            'sales_executive_id' => 'nullable|exists:mm_personnels,id',
            'concrete_pump' => 'nullable|string',
            'order_date' => 'required|date',
            'status' => 'required|integer|in:0,1,2,3',
            'mix_design_id' => 'nullable|exists:mm_mix_designs,id',
            'quantity' => 'nullable|numeric|min:0.001',
            'rate' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.mix_design_id' => 'required_without:quotation_id|exists:mm_mix_designs,id',
            'items.*.quantity' => 'required_without:quotation_id|numeric|min:0.001',
            'items.*.rate' => 'required_without:quotation_id|numeric|min:0',
        ]);

        $formattedDate = \Carbon\Carbon::parse($validated['order_date'])->format('Y-m-d');
        $validated['order_date'] = $formattedDate;

        $plantId = $customerPO->plant_id ?: (session('active_plant_id') ?: 1);

        if (empty($validated['reference'])) {
            $details = CustomerPO::generateReference($plantId, $validated['prefix'] ?? null);
            $validated['prefix'] = $details['prefix'];
            $validated['reference'] = $details['reference'];
        } else {
            if (empty($validated['prefix'])) {
                $validated['prefix'] = 'CPO';
            }
        }

        $items = [];
        if (!empty($validated['items'])) {
            $items = $validated['items'];
        } elseif (!empty($validated['mix_design_id'])) {
            $items = [[
                'mix_design_id' => $validated['mix_design_id'],
                'quantity' => $validated['quantity'],
                'rate' => $validated['rate'],
            ]];
        }

        if (empty($validated['quotation_id']) && empty($items)) {
            return redirect()->back()->withErrors(['items' => 'At least one mix design item is required.']);
        }

        DB::transaction(function () use ($customerPO, $validated, $request, $plantId, $items) {
            if ($customerPO->quotation_id && $customerPO->quotation_id != $validated['quotation_id']) {
                $otherUses = CustomerPO::where('quotation_id', $customerPO->quotation_id)
                    ->where('id', '!=', $customerPO->id)
                    ->exists();
                if (!$otherUses) {
                    Quotation::where('id', $customerPO->quotation_id)->update([
                        'is_customer_po' => 0
                    ]);
                }
            }

            if (empty($validated['quotation_id'])) {
                $customerPO->update([
                    'quotation_id' => null,
                    'prefix' => $validated['prefix'],
                    'reference' => $validated['reference'],
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'sales_executive_id' => $validated['sales_executive_id'] ?? null,
                    'concrete_pump' => $validated['concrete_pump'] ?? null,
                    'order_date' => $validated['order_date'],
                    'status' => $validated['status'],
                ]);

                $customerPO->items()->delete();
                foreach ($items as $item) {
                    $customerPO->items()->create([
                        'mix_design_id' => $item['mix_design_id'],
                        'quantity' => $item['quantity'] ?? 0,
                        'rate' => $item['rate'] ?? 0,
                        'tax_id' => null,
                        'tax_amount' => 0,
                        'untaxed_amount' => ($item['quantity'] ?? 0) * ($item['rate'] ?? 0),
                        'amount_total' => ($item['quantity'] ?? 0) * ($item['rate'] ?? 0),
                    ]);
                }
            } else {
                Quotation::where('id', $validated['quotation_id'])->update([
                    'status' => Quotation::STATUS_ACCEPTED,
                    'is_customer_po' => 1
                ]);

                $quotation = Quotation::find($validated['quotation_id']);
                $quotation->update([
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'sales_executive_id' => $validated['sales_executive_id'] ?? $quotation->sales_executive_id,
                    'quote_date' => $validated['order_date'],
                    'validity_date' => $validated['order_date'],
                ]);

                if ($request->has('mix_design_id') && $validated['mix_design_id']) {
                    $item = $quotation->items()->first();
                    if ($item) {
                        $item->update([
                            'mix_design_id' => $validated['mix_design_id'],
                            'quantity' => $validated['quantity'],
                            'rate' => $validated['rate'],
                            'untaxed_amount' => $validated['quantity'] * $validated['rate'],
                            'amount_total' => $validated['quantity'] * $validated['rate'],
                        ]);
                    } else {
                        $quotation->items()->create([
                            'mix_design_id' => $validated['mix_design_id'],
                            'quantity' => $validated['quantity'],
                            'rate' => $validated['rate'],
                            'tax_amount' => 0,
                            'untaxed_amount' => $validated['quantity'] * $validated['rate'],
                            'amount_total' => $validated['quantity'] * $validated['rate'],
                        ]);
                    }
                    $quotation->updateTotals();
                }

                $customerPO->update([
                    'quotation_id' => $validated['quotation_id'],
                    'prefix' => $validated['prefix'],
                    'reference' => $validated['reference'],
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'sales_executive_id' => $validated['sales_executive_id'] ?? ($quotation?->sales_executive_id ?? null),
                    'concrete_pump' => $validated['concrete_pump'] ?? ($quotation?->concrete_pump ?? null),
                    'order_date' => $validated['order_date'],
                    'status' => $validated['status'],
                ]);

                $customerPO->items()->delete();
                $customerPO->load('quotation.items');
                foreach ($customerPO->quotation->items as $item) {
                    $customerPO->items()->create([
                        'mix_design_id' => $item->mix_design_id,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'tax_id' => $item->tax_id,
                        'tax_amount' => $item->tax_amount,
                        'untaxed_amount' => $item->untaxed_amount,
                        'amount_total' => $item->amount_total,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Customer PO updated successfully.');
    }

    public function convertToSalesOrder(Request $request, CustomerPO $customerPO)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
        ]);
        
        $customerPO->load(['items.mixDesign']);
        
        if ($customerPO->items->isEmpty()) {
            return redirect()->back()->with('error', 'No items found in this Customer PO.');
        }

        DB::transaction(function () use ($customerPO, $validated) {
            foreach ($customerPO->items as $item) {
                $details = SalesOrder::generateOrderNo($customerPO->plant_id, 'SO');
                SalesOrder::create([
                    'prefix' => $details['prefix'],
                    'order_no' => $details['next_number'],
                    'plant_id' => $customerPO->plant_id,
                    'customer_id' => $customerPO->patron_id,
                    'site_id' => $customerPO->site_id,
                    'mix_design_id' => $item->mix_design_id,
                    'total_qty' => $validated['quantity'],
                    'produced_qty' => 0,
                    'status' => SalesOrder::STATUS_SCHEDULED,
                    'customer_po_id' => $customerPO->id,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Sales Order created successfully.');
    }
}