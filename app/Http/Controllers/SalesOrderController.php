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

        $salesOrders = SalesOrder::with(['patron', 'site', 'quotation.items.mixDesign', 'converter:username,email,id,is_active,mobile', 'workOrders'])
            ->where('plant_id', $plantId)
            ->latest()
            ->get();

        $patrons = \App\Models\Patron::select('id', 'legal_name')->orderBy('legal_name')->get();
        $sites = \App\Models\Site::select('id', 'name')->orderBy('name')->get();
        $quotations = Quotation::select('id', 'reference', 'amount_total', 'patron_id', 'site_id')
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
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        
        $validated = $request->validate([
            'quotation_id' => 'nullable|exists:mm_quotations,id',
            'patron_id' => 'required|exists:mm_patrons,id',
            'site_id' => 'required|exists:mm_sites,id',
            'order_date' => 'required|date',
            'mix_design_id' => 'required_without:quotation_id|nullable|exists:mm_mix_designs,id',
            'quantity' => 'required_without:quotation_id|nullable|numeric|min:0.001',
            'rate' => 'required_without:quotation_id|nullable|numeric|min:0',
        ]);

        $formattedDate = \Carbon\Carbon::parse($validated['order_date'])->format('Y-m-d');
        $validated['order_date'] = $formattedDate;

        $plantId = session('active_plant_id') ?: 1;
        $validated['plant_id'] = $plantId;
        $validated['status'] = SalesOrder::STATUS_CONFIRMED;

        // Create shadow quotation if quotation_id is not provided
        if (empty($validated['quotation_id'])) {
            $quotation = DB::transaction(function () use ($validated, $plantId) {
                $q = Quotation::create([
                    'plant_id' => $plantId,
                    'prefix' => 'QT',
                    'reference' => Quotation::generateReference($plantId),
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'quote_date' => $validated['order_date'],
                    'validity_date' => $validated['order_date'],
                    'status' => Quotation::STATUS_ACCEPTED,
                    'is_salesorder' => 1,
                    'amount_untaxed' => $validated['quantity'] * $validated['rate'],
                    'amount_tax' => 0,
                    'amount_total' => $validated['quantity'] * $validated['rate'],
                ]);

                \App\Models\QuotationItem::create([
                    'quotation_id' => $q->id,
                    'mix_design_id' => $validated['mix_design_id'],
                    'quantity' => $validated['quantity'],
                    'rate' => $validated['rate'],
                    'tax_amount' => 0,
                    'untaxed_amount' => $validated['quantity'] * $validated['rate'],
                    'amount_total' => $validated['quantity'] * $validated['rate'],
                ]);

                return $q;
            });

            $validated['quotation_id'] = $quotation->id;
        } else {
            Quotation::where('id', $validated['quotation_id'])->update([
                'status' => Quotation::STATUS_ACCEPTED,
                'is_salesorder' => 1
            ]);
        }

        $user = auth()->user();
        $roleName = $user->roles->pluck('name')->first() ?? 'N/A';
        $departmentName = $user->personnel?->department?->name ?? 'N/A';

        $validated['converted_by_user_id'] = $user->id;
        $validated['converted_by_role'] = $roleName;
        $validated['converted_by_department'] = $departmentName;

        SalesOrder::create($validated);

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
            'order_date' => 'required|date',
            'status' => 'required|integer|in:0,1,2,3',
            'mix_design_id' => 'nullable|exists:mm_mix_designs,id',
            'quantity' => 'nullable|numeric|min:0.001',
            'rate' => 'nullable|numeric|min:0',
        ]);

        $formattedDate = \Carbon\Carbon::parse($validated['order_date'])->format('Y-m-d');
        $validated['order_date'] = $formattedDate;

        $plantId = $salesOrder->plant_id ?: (session('active_plant_id') ?: 1);

        // If quotation_id is updated to empty (changed to Direct Sales Order)
        if (empty($validated['quotation_id'])) {
            $quotation = DB::transaction(function () use ($validated, $plantId) {
                $q = Quotation::create([
                    'plant_id' => $plantId,
                    'prefix' => 'QT',
                    'reference' => Quotation::generateReference($plantId),
                    'patron_id' => $validated['patron_id'],
                    'site_id' => $validated['site_id'],
                    'quote_date' => $validated['order_date'],
                    'validity_date' => $validated['order_date'],
                    'status' => Quotation::STATUS_ACCEPTED,
                    'is_salesorder' => 1,
                    'amount_untaxed' => ($validated['quantity'] ?? 0) * ($validated['rate'] ?? 0),
                    'amount_tax' => 0,
                    'amount_total' => ($validated['quantity'] ?? 0) * ($validated['rate'] ?? 0),
                ]);

                \App\Models\QuotationItem::create([
                    'quotation_id' => $q->id,
                    'mix_design_id' => $validated['mix_design_id'] ?? null,
                    'quantity' => $validated['quantity'] ?? 0,
                    'rate' => $validated['rate'] ?? 0,
                    'tax_amount' => 0,
                    'untaxed_amount' => ($validated['quantity'] ?? 0) * ($validated['rate'] ?? 0),
                    'amount_total' => ($validated['quantity'] ?? 0) * ($validated['rate'] ?? 0),
                ]);

                return $q;
            });
            $validated['quotation_id'] = $quotation->id;
        } else {
            Quotation::where('id', $validated['quotation_id'])->update([
                'status' => Quotation::STATUS_ACCEPTED,
                'is_salesorder' => 1
            ]);

            $quotation = Quotation::find($validated['quotation_id']);
            $quotation->update([
                'patron_id' => $validated['patron_id'],
                'site_id' => $validated['site_id'],
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
        }

        $salesOrder->update([
            'quotation_id' => $validated['quotation_id'],
            'patron_id' => $validated['patron_id'],
            'site_id' => $validated['site_id'],
            'order_date' => $validated['order_date'],
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Sales Order updated successfully.');
    }

    public function convertToWorkOrder(SalesOrder $salesOrder)
    {
        $this->authorizeModule('create');
        
        $salesOrder->load(['quotation.items.mixDesign']);
        
        if (!$salesOrder->quotation || $salesOrder->quotation->items->isEmpty()) {
            return redirect()->back()->with('error', 'No items found in the quotation associated with this Sales Order.');
        }

        DB::transaction(function () use ($salesOrder) {
            foreach ($salesOrder->quotation->items as $item) {
                // Check if a work order has already been created for this item/mix design under this sales order
                $exists = WorkOrder::where('sales_order_id', $salesOrder->id)
                    ->where('mix_design_id', $item->mix_design_id)
                    ->exists();
                    
                if (!$exists) {
                    $details = WorkOrder::generateOrderNo($salesOrder->plant_id, 'WO');
                    WorkOrder::create([
                        'prefix' => $details['prefix'],
                        'order_no' => $details['next_number'],
                        'plant_id' => $salesOrder->plant_id,
                        'customer_id' => $salesOrder->patron_id,
                        'site_id' => $salesOrder->site_id,
                        'mix_design_id' => $item->mix_design_id,
                        'total_qty' => $item->quantity,
                        'produced_qty' => 0,
                        'status' => WorkOrder::STATUS_SCHEDULED,
                        'sales_order_id' => $salesOrder->id,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Work Order created successfully.');
    }
}
