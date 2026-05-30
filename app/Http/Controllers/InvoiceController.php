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
                'subtotal'         => $source->amount_untaxed,
                'discount_total'   => $source->discount_amount,
                'tax_amount'       => $source->amount_tax,
                'adjustment'       => $source->adjustment,
                'shipping_charges' => $source->shipping_charges,
                'round_off'        => $source->rounding_value,
                'total_amount'     => $source->amount_total,
                'status'           => Invoice::STATUS_APPROVED,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);

            // 2. Create Invoice Items
            foreach ($source->items as $item) {
                $invoice->items()->create([
                    'mix_design_id'   => $type === 'bill' ? data_get($item, 'product_id') : data_get($item, 'mix_design_id'),
                    'item_name'       => data_get($item, 'product.title') ?? data_get($item, 'description'),
                    'hsn_code'        => data_get($item, 'product.hsn_code'),
                    'quantity'        => data_get($item, 'product_quantity') ?? data_get($item, 'quantity'),
                    'uom_id'          => data_get($item, 'product_uom') ?? data_get($item, 'uom_id'),
                    'price_unit'      => data_get($item, 'unit_price'),
                    'discount_type'   => data_get($item, 'discount_type'),
                    'discount'        => data_get($item, 'discount_amount'),
                    'discount_amount' => data_get($item, 'total_discount'),
                    'subtotal'        => data_get($item, 'price_subtotal'),
                    'line_tax_amount' => data_get($item, 'price_tax'),
                    'line_total'      => data_get($item, 'price_total'),
                    'tax_id'          => data_get($item, 'tax_id'),
                ]);
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
