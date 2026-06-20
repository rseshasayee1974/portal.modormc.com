<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\Quotation;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'sales_orders';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $salesOrders = SalesOrder::with(['patron', 'site', 'items.mixDesign', 'quotation.items.mixDesign', 'converter:username,email,id,is_active,mobile', 'workOrders', 'salesExecutive'])
            ->where('plant_id', $plantId)
            ->latest()
            ->get();

        $patrons = \App\Models\Patron::select('id', 'legal_name')->orderBy('legal_name')->get();
        $sites = \App\Models\Site::select('id', 'name')->orderBy('name')->get();
        $quotations = Quotation::with(['items:id,quotation_id,mix_design_id,quantity,rate,tax_id,tax_amount,untaxed_amount,amount_total'])
            ->select('id', 'reference', 'amount_total', 'patron_id', 'site_id', 'is_salesorder')
            ->where('plant_id', $plantId)
            ->where('is_salesorder', 0)
            ->orderBy('reference')
            ->get();
        $mixDesigns = \App\Models\MixDesign::select('id', 'design_name')->orderBy('design_name')->get();

        return Inertia::render('SalesOrders/Index', [
            'salesOrders' => $salesOrders,
            'patrons' => $patrons,
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
    
    // Set status based on whether quotation exists
    $validated['status'] = empty($validated['quotation_id']) 
        ? SalesOrder::STATUS_DRAFT 
        : SalesOrder::STATUS_CONFIRMED;

    $user = auth()->user();
    $validated['converted_by_user_id'] = $user->id;

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
                'is_salesorder' => 1
            ]);
        }

        // Create the Sales Order
        $salesOrder = SalesOrder::create([
            'plant_id' => $plantId,
            'quotation_id' => $validated['quotation_id'] ?: null,
            'patron_id' => $validated['patron_id'],
            'site_id' => $validated['site_id'],
            'sales_executive_id' => $validated['sales_executive_id'] ?? ($quote?->sales_executive_id ?? null),
            'concrete_pump' => $validated['concrete_pump'] ?? ($quote?->concrete_pump ?? null),
            'order_date' => $validated['order_date'],
            'status' => $validated['status'], // Now uses the conditional status above
            'converted_by_user_id' => $validated['converted_by_user_id'],
        ]);

        if (empty($validated['quotation_id'])) {
            // Direct Sales Order: create multiple items
            foreach ($items as $item) {
                $salesOrder->items()->create([
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
            // Copy items from quotation to sales order items
            $salesOrder->load('quotation.items');
            foreach ($salesOrder->quotation->items as $item) {
                $salesOrder->items()->create([
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

    return redirect()->back()->with('success', 'Sales Order created successfully.');
}

    public function destroy(SalesOrder $salesOrder)
    {
        $this->authorizeModule('delete');
        
        if ($salesOrder->quotation_id) {
            Quotation::where('id', $salesOrder->quotation_id)->update([
                'is_salesorder' => 0
            ]);
        }

        $salesOrder->delete();

        return redirect()->back()->with('success', 'Sales Order deleted.');
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $this->authorizeModule('update');
        
        $validated = $request->validate([
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

        $plantId = $salesOrder->plant_id ?: (session('active_plant_id') ?: 1);

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

        DB::transaction(function () use ($salesOrder, $validated, $request, $plantId, $items) {
            if ($salesOrder->quotation_id && $salesOrder->quotation_id != $validated['quotation_id']) {
                $otherUses = SalesOrder::where('quotation_id', $salesOrder->quotation_id)
                    ->where('id', '!=', $salesOrder->id)
                    ->exists();
                if (!$otherUses) {
                    Quotation::where('id', $salesOrder->quotation_id)->update([
                        'is_salesorder' => 0
                    ]);
                }
            }

            if (empty($validated['quotation_id'])) {
                $salesOrder->update([
                    'quotation_id' => null,
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'sales_executive_id' => $validated['sales_executive_id'] ?? null,
                    'concrete_pump' => $validated['concrete_pump'] ?? null,
                    'order_date' => $validated['order_date'],
                    'status' => $validated['status'],
                ]);

                $salesOrder->items()->delete();
                foreach ($items as $item) {
                    $salesOrder->items()->create([
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
                    'is_salesorder' => 1
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

                $salesOrder->update([
                    'quotation_id' => $validated['quotation_id'],
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'sales_executive_id' => $validated['sales_executive_id'] ?? ($quotation?->sales_executive_id ?? null),
                    'concrete_pump' => $validated['concrete_pump'] ?? ($quotation?->concrete_pump ?? null),
                    'order_date' => $validated['order_date'],
                    'status' => $validated['status'],
                ]);

                $salesOrder->items()->delete();
                $salesOrder->load('quotation.items');
                foreach ($salesOrder->quotation->items as $item) {
                    $salesOrder->items()->create([
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

        return redirect()->back()->with('success', 'Sales Order updated successfully.');
    }

    public function convertToWorkOrder(Request $request, SalesOrder $salesOrder)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
        ]);
        
        $salesOrder->load(['items.mixDesign']);
        
        if ($salesOrder->items->isEmpty()) {
            return redirect()->back()->with('error', 'No items found in this Sales Order.');
        }

        DB::transaction(function () use ($salesOrder, $validated) {
            foreach ($salesOrder->items as $item) {
                $details = WorkOrder::generateOrderNo($salesOrder->plant_id, 'WO');
                WorkOrder::create([
                    'prefix' => $details['prefix'],
                    'order_no' => $details['next_number'],
                    'plant_id' => $salesOrder->plant_id,
                    'customer_id' => $salesOrder->patron_id,
                    'site_id' => $salesOrder->site_id,
                    'mix_design_id' => $item->mix_design_id,
                    'total_qty' => $validated['quantity'],
                    'produced_qty' => 0,
                    'status' => WorkOrder::STATUS_SCHEDULED,
                    'sales_order_id' => $salesOrder->id,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Work Order created successfully.');
    }
}
