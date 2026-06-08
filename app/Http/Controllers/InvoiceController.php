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

class InvoiceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'invoices';

    /**
     * Globally common function to generate an Invoice from a source document (PO, SO, etc.)
     */
    public static function createFromSource($source, string $type, array $params = []): Invoice
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($source, $type, $params) {
            $plantId = $params['plant_id'] ?? session('active_plant_id');
            $userId  = Auth::id();

            $subtotalSum = 0;
            $taxSum = 0;
            $discountSum = 0;
            
            $itemsData = [];
            
            foreach ($source->items as $item) {
                if ($type === 'bill') {
                    // For a Purchase Bill, quantity is the received/invoiced quantity
                    $qty = (float) ($item->invoiced_quantity > 0 ? $item->invoiced_quantity : ($item->received_quantity > 0 ? $item->received_quantity : $item->received_quantity));
                    $priceUnit = (float) data_get($item, 'unit_price');
                    
                    // Recalculate discount
                    $discountType = data_get($item, 'discount_type');
                    $discountVal = (float) data_get($item, 'discount_amount');
                    $lineSubtotalBeforeDiscount = $qty * $priceUnit;
                    
                    if ($discountType === 'percentage') {
                        $lineDiscount = ($lineSubtotalBeforeDiscount * $discountVal) / 100;
                    } else {
                        // Scale the fixed discount proportionally if quantity changed from ordered quantity
                        $orderedQty = (float) data_get($item, 'product_quantity');
                        if ($qty == $orderedQty || $orderedQty == 0) {
                            $lineDiscount = $discountVal;
                        } else {
                            $lineDiscount = ($discountVal / $orderedQty) * $qty;
                        }
                    }
                    
                    $lineSubtotal = $lineSubtotalBeforeDiscount - $lineDiscount;
                    
                    // Recalculate tax
                    $taxRate = 0;
                    $taxId = data_get($item, 'tax_id');
                    if ($taxId) {
                        $tax = Tax::find($taxId);
                        if ($tax) {
                            $taxRate = (float) $tax->tax_rate;
                        }
                    }
                    
                    $lineTax = ($lineSubtotal * $taxRate) / 100;
                    $lineTotal = $lineSubtotal + $lineTax;
                } else {
                    // For sales/invoice, use original values
                    $qty = (float) (data_get($item, 'product_quantity') ?? data_get($item, 'quantity') ?? 0);
                    $priceUnit = (float) data_get($item, 'unit_price');
                    $lineDiscount = (float) data_get($item, 'total_discount');
                    $lineSubtotal = (float) data_get($item, 'price_subtotal');
                    $lineTax = (float) data_get($item, 'price_tax');
                    $lineTotal = (float) data_get($item, 'price_total');
                    $taxId = data_get($item, 'tax_id');
                }
                
                $subtotalSum += $lineSubtotal;
                $taxSum += $lineTax;
                $discountSum += $lineDiscount;
                
                $itemsData[] = [
                    'mix_design_id'   => $type === 'bill' ? data_get($item, 'product_id') : data_get($item, 'mix_design_id'),
                    'item_name'       => data_get($item, 'product.title') ?? data_get($item, 'description'),
                    'hsn_code'        => data_get($item, 'product.hsn_code'),
                    'quantity'        => $qty,
                    'uom_id'          => data_get($item, 'product_uom') ?? data_get($item, 'uom_id'),
                    'price_unit'      => $priceUnit,
                    'discount_type'   => data_get($item, 'discount_type'),
                    'discount'        => data_get($item, 'discount_amount'),
                    'discount_amount' => $lineDiscount,
                    'subtotal'        => $lineSubtotal,
                    'line_tax_amount' => $lineTax,
                    'line_total'      => $lineTotal,
                    'tax_id'          => $taxId,
                ];
            }

            $subtotal = $type === 'bill' ? ($subtotalSum - $source->discount_amount) : $source->amount_untaxed;
            $discountTotal = $type === 'bill' ? ($discountSum + $source->discount_amount) : $source->discount_amount;
            $taxAmount = $type === 'bill' ? $taxSum : $source->amount_tax;
            
            $adjustment = $source->adjustment;
            $shippingCharges = $source->shipping_charges;
            
            if ($type === 'bill') {
                $totalAmount = $subtotal + $taxAmount + $shippingCharges + $adjustment;
                $roundedTotal = round($totalAmount);
                $roundOff = $roundedTotal - $totalAmount;
                $totalAmount = $roundedTotal;
            } else {
                $roundOff = $source->rounding_value;
                $totalAmount = $source->amount_total;
            }

            // 1. Create the Invoice Header
            $invoice = Invoice::create([
                'plant_id'         => $plantId,
                'partner_id'       => $params['partner_id'] ?? ($type === 'bill' ? $source->vendor_id : $source->customer_id),
                'account_id'       => $params['account_id'] ?? null,
                'invoice_type'     => $type,
                'invoice_label'    => $params['invoice_label'] ?? null,
                'ref_id'           => $source->id,
                'ref_title'        => $params['ref_title'] ?? $source->po_number ?? $source->so_number ?? $source->ref_no,
                'invoice_date'     => $params['invoice_date'] ?? now(),
                'due_date'         => $params['due_date'] ?? $source->due_date,
                'subtotal'         => $subtotal,
                'global_discount'   => $discountTotal,
                'tax_amount'       => $taxAmount,
                'adjustment'       => $adjustment,
                'shipping_charges' => $shippingCharges,
                'round_off'        => $roundOff,
                'total_amount'     => $totalAmount,
                'balance_amount'   => $totalAmount,
                'status'           => Invoice::STATUS_APPROVED,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);

            // 2. Create Invoice Items
            foreach ($itemsData as $itemData) {
                $invoice->items()->create($itemData);
            }

            // 3. Sync Tax Splits (Generates mm_order_taxes records)
            $invoice->syncTaxSplits();

            // 4. Automated Accounting Posting
            if ($invoice->status === Invoice::STATUS_APPROVED || $invoice->status === Invoice::STATUS_PAID) {
                $invoice->postToAccounting();
            }

            // 5. Update Source Status if applicable (e.g. Dispatch)
            if ($source instanceof \App\Models\Dispatch) {
                $source->status()->updateOrCreate(
                    ['dispatch_id' => $source->id],
                    [
                        'plant_id'       => $source->plant_id,
                        'invoice_id'     => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'invoice_date'   => $invoice->invoice_date,
                        'invoice_status' => 1,
                    ]
                );
            }

            return $invoice;
        });
    }

    public function index()
    {
        $this->authorizeModule('menu'); 
        $plantId = session('active_plant_id');
        return Inertia::render('Invoices/Index', [
            'invoices' => Invoice::withTrashed()
                ->with([
                    'partner:id,legal_name',
                    'account:id,title',
                    'destroyer:id,username',
                    'items.uom:id,unit_code',
                    'items.tax',
                ])
                ->where('invoice_type', 'sales')
                ->where('plant_id', $plantId)->where('deleted_at',null)
                ->latest()
                ->get(),
            'patrons' => toSelectOptions(PatronsDropdown(), 'legal_name'),
            'taxes'   => collect(TaxesDropdown('sales'))->map(fn($t) => [
                'label' => $t->tax_name,
                'value' => $t->id,
                'rate'  => $t->tax_rate,
            ]),
            'accounts' => toSelectOptions(LedgersDropdown('REVENUE'), 'title'),
            'mixdesign' => MixDesignsOptions(),
            'units'    => toSelectOptions(Productunit(), 'unit_code'),
            'machines' => toSelectOptions(MachinesDropdown(), 'registration', 'registration'),
            'instant_invoice_patron' => CustomSetting::getForModule(session('active_entity_id'), 'invoice')['instant_invoice_patron'] ?? 0,
            'next_invoice_number' => Invoice::generateNumber($plantId, 'sales')['full_number'],
            'next_invoice_details' => Invoice::generateNumber($plantId, 'sales'),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $plantId) {
            $validated = $request->validated();
            
            // Auto-generate numbering if not provided
            if (empty($validated['invoice_number'])) {
                $details = Invoice::generateNumber($plantId, $validated['invoice_type'] ?? 'sales');
                $validated['prefix'] = $details['prefix'];
                $validated['invoice_number'] = $details['next_number'];
            }

            // Ensure mandatory fields and defaults
            $invoice = Invoice::createWithItems(array_merge($validated, [
                'plant_id'        => $plantId,
                'status'          => Invoice::STATUS_APPROVED,
                'due_date'        => $validated['due_date'] ?? $validated['invoice_date'],
                'einvoice_status' => 0,
                'created_by'      => Auth::id(),
            ]));

            // If dispatch-wise invoicing, update the dispatches with the invoice info
            if ($request->has('dispatch_ids') && is_array($request->dispatch_ids)) {
                \App\Models\DispatchStatus::whereIn('dispatch_id', $request->dispatch_ids)->update([
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date'   => $invoice->invoice_date,
                    'invoice_status' => 1,
                ]);
            }

            $invoice->postToAccounting();

            return redirect()->back()->with('success', 'Invoice created successfully as ' . $invoice->invoice_number);
        });
    }

    public function getUninvoicedDispatches(Request $request)
    {
        $this->authorizeModule('menu');
        
        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $startDate = \Illuminate\Support\Carbon::parse($validated['start_date'])->startOfDay();
        $endDate   = \Illuminate\Support\Carbon::parse($validated['end_date'])->endOfDay();

        $dispatches = \App\Models\Dispatch::with(['mixDesign', 'truck', 'status'])
            ->where('customer_id', $validated['partner_id'])
            ->whereBetween('dispatch_time', [$startDate, $endDate])
            ->whereHas('status', function($q) {
                $q->whereNull('invoice_id');
            })
            ->latest()
            ->get();

        return response()->json($dispatches);
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

    public function show(Invoice $invoice)
    {
        // Handle manually if needed or use withTrashed in Route binding if possible.
        // But since $invoice is already resolved by Route Model Binding, if it was deleted, it would 404.
        // We should change the Route Binding or handle it here.
        $this->authorizeModule('view');
        
        return response()->json(
            $invoice->load([
                'partner:id,legal_name',
                'items.tax',
                'items.uom:id,unit_code',
                'orderTaxes',
                'account:id,title',
                'createdBy:id,username',
                'destroyer:id,username',
            ])
        );
       
    }

    public function outstanding(Request $request)
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');
// dd($request->all());
        $query = Invoice::where('plant_id', $plantId)
            ->where('status', 'approved')
            ->where('balance_amount', '>', 0);

        if ($request->has('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->has('type')) {
            // 'sales' or 'bill'
            $query->where('invoice_type', $request->type);
        }
// dd($query->latest()->get());
        return response()->json($query->latest()->get());
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorizeModule('delete');

        $invoice->delete();
        return redirect()->back()->with('success', 'Invoice voided.');
    }
}