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
                    'product_id'      => $item->product_id,
                    'item_name'       => $item->product?->title ?? $item->description,
                    'hsn_code'        => $item->product?->hsn_code,
                    'quantity'        => $item->product_quantity ?? $item->quantity,
                    // 'uom_id'          => $item->product_uom ?? $item->uom_id,
                    'price_unit'      => $item->unit_price,
                    'discount_type'   => $item->discount_type,
                    'discount'        => $item->discount_amount,
                    'discount_amount' => $item->total_discount,
                    'subtotal'        => $item->price_subtotal,
                    'line_tax_amount' => $item->price_tax,
                    'line_total'      => $item->price_total,
                    'tax_id'          => $item->tax_id,
                ]);
            }

            // 3. Sync Tax Splits (Generates mm_order_taxes records)
            $invoice->syncTaxSplits();

            return $invoice;
        });
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');
        return Inertia::render('Invoices/Index', [
            'invoices' => Invoice::with([
                    'partner:id,legal_name',
                    'account:id,title',
                ])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'patrons' => toSelectOptions(PatronsDropdown($plantId), 'legal_name'),
            'taxes'   => collect(TaxesDropdown($plantId, 'sales'))->map(fn($t) => [
                'label' => $t->tax_name,
                'value' => $t->id,
                'rate'  => $t->tax_rate,
            ]),
            'accounts' => toSelectOptions(LedgersDropdown(), 'title'),
            'mixdesign' => MixDesignsOptions($plantId),
            'units'    => toSelectOptions(Productunit(), 'unit_code'),
            'instant_invoice_patron' => CustomSetting::getForModule(session('active_entity_id'), 'invoice')['instant_invoice_patron'] ?? 0,
            'next_invoice_number' => Invoice::generateNumber($plantId),
        ]);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');

        $invoice = Invoice::createWithItems(array_merge($request->validated(), [
            'plant_id'   => $plantId,
            'status'     => Invoice::STATUS_PAID,
            'created_by' => Auth::id(),
        ]));

        return redirect()->back()->with('success', 'Invoice created successfully as ' . $invoice->invoice_number);
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
        $this->authorizeModule('view');
        
        return response()->json(
            $invoice->load([
                'partner:id,legal_name',
                'items.tax',
                'items.uom:id,unit_code',
                'orderTaxes',
                'account:id,title',
                'createdBy:id,username'
            ])
        );
       
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorizeModule('delete');

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_APPROVED])) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete an approved or paid invoice.']);
        }

        $invoice->delete();
        return redirect()->back()->with('success', 'Invoice voided.');
    }
}
