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

        $customerPOs = CustomerPO::with(['patron', 'site', 'items.mixDesign', 'items.pumpRates', 'quotation.items.mixDesign', 'quotation.items.pumpRates', 'converter:username,email,id,is_active,mobile', 'salesOrders', 'salesExecutive'])
            ->where('plant_id', $plantId)
            ->latest()
            ->get();

        $sites = SitesDropdown();
        $quotations = Quotation::with(['items.mixDesign', 'items.pumpRates'])
            ->select('id', 'reference', 'amount_total', 'patron_id', 'site_id', 'is_customer_po', 'sales_executive_id')
            ->where('plant_id', $plantId)
            ->where('is_customer_po', 0)
            ->orderBy('reference')
            ->get();
        $mixDesigns = MixDesignsDropdown();

        return Inertia::render('CustomerPOs/Index', [
            'customerPOs' => $customerPOs,
            'patrons' =>  PatronsDropdown(['Customer']),
            'sites' => $sites,
            'quotations' => $quotations,
            'mixDesigns' => $mixDesigns,
            'taxes' => TaxesDropdown('sales',['GST','IGST']),
            'salesExecutives' => SalesExecutivesDropdown(),
            'concretePumpOptions' => ConcretePumpDropdown(),
            'pumpTypeOptions' => PumpTypeDropdown(),
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
            // 'concrete_pump' => 'nullable|integer|exists:mm_machines,id',
            'is_tax_inclusive' => 'nullable|boolean',
            'order_date' => 'required|date',
            'notes' => 'nullable|string',
            'mix_design_id' => 'nullable|exists:mm_mix_designs,id',
            'quantity' => 'nullable|numeric|min:0.001',
            'rate' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|exists:mm_taxes,id',
            'tax_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.mix_design_id' => 'required_without:quotation_id|exists:mm_mix_designs,id',
            'items.*.quantity' => 'required_without:quotation_id|numeric|min:0.001',
            'items.*.rate' => 'required_without:quotation_id|numeric|min:0',
            'items.*.tax_id' => 'nullable|exists:mm_taxes,id',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.pump_rates' => 'nullable|array',
            'items.*.pump_rates.*.pump_type' => 'required|string|max:100',
            'items.*.pump_rates.*.pump_rate' => 'required|numeric|min:0',
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
                'tax_id' => $validated['tax_id'] ?? null,
                'tax_amount' => $validated['tax_amount'] ?? 0,
            ]];
        }

        if (empty($validated['quotation_id']) && empty($items)) {
            return redirect()->back()->withErrors(['items' => 'At least one mix design item is required.']);
        }

        DB::transaction(function () use ($validated, $plantId, $items) {
            $quote = null;
            if (!empty($validated['quotation_id'])) {
                $quote = Quotation::find($validated['quotation_id']);
                if ($quote) {
                    $quote->update([
                        'status' => Quotation::STATUS_ACCEPTED,
                        'is_customer_po' => 1
                    ]);
                }
            }

            $isTaxInclusive = (bool)($validated['is_tax_inclusive'] ?? ($quote?->is_tax_inclusive ?? false));

            $customerPO = CustomerPO::create([
                'plant_id' => $plantId,
                'prefix' => $validated['prefix'],
                'reference' => $validated['reference'],
                'quotation_id' => $validated['quotation_id'] ?: null,
                'patron_id' => $validated['patron_id'],
                'site_id' => $validated['site_id'],
                'notes' => $validated['notes'] ?? null,
                'sales_executive_id' => $validated['sales_executive_id'] ?? ($quote?->sales_executive_id ?? null),
                // 'concrete_pump' => $validated['concrete_pump'] ?? ($quote?->concrete_pump ?? null),
                'is_tax_inclusive' => $isTaxInclusive,
                'order_date' => $validated['order_date'],
                'status' => $validated['status'],
                'converted_by_user_id' => $validated['converted_by_user_id'],
            ]);

            if (empty($validated['quotation_id'])) {
                foreach ($items as $item) {
                    $qty = (float)($item['quantity'] ?? 0);
                    $rate = (float)($item['rate'] ?? 0);
                    
                    $taxId = $item['tax_id'] ?? null;
                    if ($isTaxInclusive) {
                        $amountTotal = $qty * $rate;
                        $taxRate = 0.0;
                        if ($taxId) {
                            $taxModel = \App\Models\Tax::find($taxId);
                            $taxRate = $taxModel ? (float)($taxModel->tax_rate ?? $taxModel->rate ?? 0) : 0.0;
                        }
                        $taxAmount = $amountTotal - ($amountTotal / (1 + $taxRate / 100));
                        $untaxedAmount = $amountTotal - $taxAmount;
                    } else {
                        $taxAmount = (float)($item['tax_amount'] ?? 0);
                        $untaxedAmount = $qty * $rate;
                        $amountTotal = $untaxedAmount + $taxAmount;
                    }

                    $customerPO->items()->create([
                        'mix_design_id' => $item['mix_design_id'],
                        'quantity' => $qty,
                        'rate' => $rate,
                        'tax_id' => $taxId,
                        'tax_amount' => round($taxAmount, 2),
                        'untaxed_amount' => round($untaxedAmount, 2),
                        'amount_total' => round($amountTotal, 2),
                    ]);
                    // Sync pump rates if provided
                    $createdItem = $customerPO->items()->latest('id')->first();
                    if ($createdItem && !empty($item['pump_rates'])) {
                        $createdItem->syncPumpRates($item['pump_rates']);
                    }
                }
            } else {
                $customerPO->load('quotation.items.pumpRates');
                foreach ($customerPO->quotation->items as $item) {
                    $createdItem = $customerPO->items()->create([
                        'mix_design_id' => $item->mix_design_id,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'tax_id' => $item->tax_id,
                        'tax_amount' => $item->tax_amount,
                        'untaxed_amount' => $item->untaxed_amount,
                        'amount_total' => $item->amount_total,
                    ]);
                    // Carry pump rates from quotation item → CPO item
                    $sourcePumpRates = $item->pumpRates->map(fn($pr) => [
                        'pump_type' => $pr->pump_type,
                        'pump_rate' => $pr->pump_rate,
                    ])->toArray();
                    if (!empty($sourcePumpRates)) {
                        $createdItem->syncPumpRates($sourcePumpRates);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Customer PO created successfully.');
    }

    public function destroy(CustomerPO $customerPO)
    {
        $this->authorizeModule('delete');
        
        if ($customerPO->quotation_id) {
            $quote = Quotation::find($customerPO->quotation_id);
            if ($quote) {
                $quote->update([
                    'is_customer_po' => 0
                ]);
            }
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
            // 'concrete_pump' => 'nullable|integer|exists:mm_machines,id',
            'is_tax_inclusive' => 'nullable|boolean',
            'order_date' => 'required|date',
            'status' => 'required|integer|in:0,1,2,3',
            'mix_design_id' => 'nullable|exists:mm_mix_designs,id',
            'quantity' => 'nullable|numeric|min:0.001',
            'rate' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|exists:mm_taxes,id',
            'tax_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'notes' => 'nullable|string',
            'items.*.mix_design_id' => 'required_without:quotation_id|exists:mm_mix_designs,id',
            'items.*.quantity' => 'required_without:quotation_id|numeric|min:0.001',
            'items.*.rate' => 'required_without:quotation_id|numeric|min:0',
            'items.*.tax_id' => 'nullable|exists:mm_taxes,id',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.pump_rates' => 'nullable|array',
            'items.*.pump_rates.*.pump_type' => 'required|string|max:100',
            'items.*.pump_rates.*.pump_rate' => 'required|numeric|min:0',
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
                'id' => $customerPO->items()->first()?->id ?? null,
                'mix_design_id' => $validated['mix_design_id'],
                'quantity' => $validated['quantity'],
                'rate' => $validated['rate'],
                'tax_id' => $validated['tax_id'] ?? null,
                'tax_amount' => $validated['tax_amount'] ?? 0,
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
                    $oldQuote = Quotation::find($customerPO->quotation_id);
                    if ($oldQuote) {
                        $oldQuote->update([
                            'is_customer_po' => 0
                        ]);
                    }
                }
            }

            $isTaxInclusive = (bool)($validated['is_tax_inclusive'] ?? false);

            if (empty($validated['quotation_id'])) {
                $customerPO->update([
                    'quotation_id' => null,
                    'prefix' => $validated['prefix'],
                    'reference' => $validated['reference'],
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'notes' => $validated['notes'] ?? null,
                    'sales_executive_id' => $validated['sales_executive_id'] ?? null,
                    // 'concrete_pump' => $validated['concrete_pump'] ?? null,
                    'is_tax_inclusive' => $isTaxInclusive,
                    'order_date' => $validated['order_date'],
                    'status' => $validated['status'],
                ]);

                // Delete items not in the payload
                $itemIds = collect($items)->pluck('id')->filter()->toArray();
                foreach ($customerPO->items()->whereNotIn('id', $itemIds)->get() as $oldItem) {
                    $oldItem->delete();
                }

                foreach ($items as $item) {
                    $qty = (float)($item['quantity'] ?? 0);
                    $rate = (float)($item['rate'] ?? 0);
                    
                    $taxId = $item['tax_id'] ?? null;
                    if ($isTaxInclusive) {
                        $amountTotal = $qty * $rate;
                        $taxRate = 0.0;
                        if ($taxId) {
                            $taxModel = \App\Models\Tax::find($taxId);
                            $taxRate = $taxModel ? (float)($taxModel->tax_rate ?? $taxModel->rate ?? 0) : 0.0;
                        }
                        $taxAmount = $amountTotal - ($amountTotal / (1 + $taxRate / 100));
                        $untaxedAmount = $amountTotal - $taxAmount;
                    } else {
                        $taxAmount = (float)($item['tax_amount'] ?? 0);
                        $untaxedAmount = $qty * $rate;
                        $amountTotal = $untaxedAmount + $taxAmount;
                    }

                    $itemData = [
                        'mix_design_id' => $item['mix_design_id'],
                        'quantity' => $qty,
                        'rate' => $rate,
                        'tax_id' => $taxId,
                        'tax_amount' => round($taxAmount, 2),
                        'untaxed_amount' => round($untaxedAmount, 2),
                        'amount_total' => round($amountTotal, 2),
                    ];

                    if (!empty($item['id'])) {
                        $existingItem = $customerPO->items()->find($item['id']);
                        if ($existingItem) {
                            $existingItem->update($itemData);
                            $createdItem = $existingItem;
                        } else {
                            $createdItem = $customerPO->items()->create($itemData);
                        }
                    } else {
                        $createdItem = $customerPO->items()->create($itemData);
                    }

                    if ($createdItem && !empty($item['pump_rates'])) {
                        $createdItem->syncPumpRates($item['pump_rates']);
                    }
                }
            } else {
                $quotation = Quotation::find($validated['quotation_id']);
                if ($quotation) {
                    $quotation->update([
                        'status' => Quotation::STATUS_ACCEPTED,
                        'is_customer_po' => 1,
                        'patron_id' => $validated['patron_id'],
                        'site_id' => $validated['site_id'],
                        'sales_executive_id' => $validated['sales_executive_id'] ?? $quotation->sales_executive_id,
                        'is_tax_inclusive' => $isTaxInclusive,
                        'quote_date' => $validated['order_date'],
                        'validity_date' => $validated['order_date'],
                    ]);
                }

                if ($request->has('mix_design_id') && $validated['mix_design_id']) {
                    $item = $quotation->items()->first();
                    $taxAmount = (float)($request->input('tax_amount') ?? 0);
                    $qty = (float)$validated['quantity'];
                    $rate = (float)$validated['rate'];
                    $taxId = $request->input('tax_id');
                    
                    if ($isTaxInclusive) {
                        $amountTotal = $qty * $rate;
                        $taxRate = 0.0;
                        if ($taxId) {
                            $taxModel = \App\Models\Tax::find($taxId);
                            $taxRate = $taxModel ? (float)($taxModel->tax_rate ?? $taxModel->rate ?? 0) : 0.0;
                        }
                        $taxAmount = $amountTotal - ($amountTotal / (1 + $taxRate / 100));
                        $untaxedAmount = $amountTotal - $taxAmount;
                    } else {
                        $untaxedAmount = $qty * $rate;
                        $amountTotal = $untaxedAmount + $taxAmount;
                    }

                    $untaxedAmount = round($untaxedAmount, 2);
                    $taxAmount = round($taxAmount, 2);
                    $amountTotal = round($amountTotal, 2);

                    if ($item) {
                        $item->update([
                            'mix_design_id' => $validated['mix_design_id'],
                            'quantity' => $qty,
                            'rate' => $rate,
                            'tax_id' => $taxId,
                            'tax_amount' => $taxAmount,
                            'untaxed_amount' => $untaxedAmount,
                            'amount_total' => $amountTotal,
                        ]);
                    } else {
                        $quotation->items()->create([
                            'mix_design_id' => $validated['mix_design_id'],
                            'quantity' => $qty,
                            'rate' => $rate,
                            'tax_id' => $taxId,
                            'tax_amount' => $taxAmount,
                            'untaxed_amount' => $untaxedAmount,
                            'amount_total' => $amountTotal,
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
                    'notes' => $validated['notes'] ?? null,
                    'sales_executive_id' => $validated['sales_executive_id'] ?? ($quotation?->sales_executive_id ?? null),
                    // 'concrete_pump' => $validated['concrete_pump'] ?? ($quotation?->concrete_pump ?? null),
                    'is_tax_inclusive' => $isTaxInclusive,
                    'order_date' => $validated['order_date'],
                    'status' => $validated['status'],
                ]);

                foreach ($customerPO->items as $item) {
                    $item->delete();
                }
                $customerPO->load('quotation.items.pumpRates');
                foreach ($customerPO->quotation->items as $item) {
                    $createdItem = $customerPO->items()->create([
                        'mix_design_id' => $item->mix_design_id,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'tax_id' => $item->tax_id,
                        'tax_amount' => $item->tax_amount,
                        'untaxed_amount' => $item->untaxed_amount,
                        'amount_total' => $item->amount_total,
                    ]);
                    
                    // Copy pump rates
                    $sourcePumpRates = $item->pumpRates->map(fn($pr) => [
                        'pump_type' => $pr->pump_type,
                        'pump_rate' => $pr->pump_rate,
                    ])->toArray();
                    if (!empty($sourcePumpRates)) {
                        $createdItem->syncPumpRates($sourcePumpRates);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Customer PO updated successfully.');
    }

    public function convertToSalesOrder(Request $request, CustomerPO $customerPO)
    {
        $this->authorizeModule('create');
        
        $customerPO->load(['items.mixDesign']);
        
        if ($customerPO->items->isEmpty()) {
            return redirect()->back()->with('error', 'No items found in this Customer PO.');
        }

        if ($request->has('items')) {
            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.item_id' => 'required|exists:mm_customer_po_items,id',
                'items.*.quantity' => 'required|numeric|min:0',
                'items.*.concrete_pump' => 'nullable|integer|exists:mm_machines,id',
            ]);
            
            DB::transaction(function () use ($customerPO, $validated) {
                if ((int)$customerPO->status === CustomerPO::STATUS_DRAFT) {
                    $customerPO->update(['status' => CustomerPO::STATUS_CONFIRMED]);
                }

                foreach ($validated['items'] as $itemData) {
                    if ((float)$itemData['quantity'] <= 0) {
                        continue;
                    }
                    
                    $poItem = $customerPO->items()->find($itemData['item_id']);
                    if (!$poItem) continue;

                    $details = SalesOrder::generateOrderNo($customerPO->plant_id, 'SO');
                    SalesOrder::create([
                        'prefix' => $details['prefix'],
                        'order_no' => $details['next_number'],
                        'plant_id' => $customerPO->plant_id,
                        'customer_id' => $customerPO->patron_id,
                        'site_id' => $customerPO->site_id,
                        'mix_design_id' => $poItem->mix_design_id,
                        'total_qty' => $itemData['quantity'],
                        'produced_qty' => 0,
                        'status' => SalesOrder::STATUS_SCHEDULED,
                        'customer_po_id' => $customerPO->id,
                        'concrete_pump' => $itemData['concrete_pump'],
                    ]);
                }
            });
        } else {
            $validated = $request->validate([
                'quantity' => 'required|numeric|min:0.001',
            ]);
            
            DB::transaction(function () use ($customerPO, $validated) {
                if ((int)$customerPO->status === CustomerPO::STATUS_DRAFT) {
                    $customerPO->update(['status' => CustomerPO::STATUS_CONFIRMED]);
                }

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
        }

        return redirect()->back()->with('success', 'Sales Orders created successfully.');
    }
}