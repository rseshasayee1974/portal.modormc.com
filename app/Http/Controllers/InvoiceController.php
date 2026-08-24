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
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'invoices';

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
                $details = Invoice::generateNumber($plantId, $validated['invoice_type'] ?? 'sales', $validated['account_id'] ?? null);
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
                $dispatches = \App\Models\Dispatch::whereIn('id', $request->dispatch_ids)->get();
                foreach ($dispatches as $dispatch) {
                    $dispatch->invoice($invoice);
                }
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

        $dispatches = \App\Models\Dispatch::with(['mixDesign', 'truck', 'status', 'batch', 'uom'])
            ->where('customer_id', $validated['partner_id'])
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('dispatch_time', [$startDate, $endDate])
                  ->orWhereBetween('created_at', [$startDate, $endDate]);
            })
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

    public function printTaxInvoice(Request $request, $id)
    {
        $realId = $id;
        try { $realId = decrypt($id); } catch (\Exception $e) { }

        $invoice = Invoice::with([
            'plant.entity.bankAccounts',
            'plant.addresses.state',
            'partner.addresses.state',
            'items.tax',
            'items.uom',
        ])->findOrFail($realId);

        $copyType = $request->get('copy') ?? ($invoice->is_duplicate ? 'DUPLICATE' : 'ORIGINAL');

        if ($request->get('download') === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.invoices.tax_invoice', [
                'invoice' => $invoice,
                'copy_type' => $copyType,
            ]);
            return $pdf->download("Tax_Invoice_{$invoice->full_number}.pdf");
        }

        return view('pdfs.invoices.tax_invoice', [
            'invoice' => $invoice,
            'copy_type' => $copyType,
        ]);
    }

    public function destroy($id)
    {
        $this->authorizeModule('delete');

        $realId = $id;
        try {
            $realId = decrypt($id);
        } catch (\Exception $e) {
            // If it wasn't encrypted or failed decrypt, try using raw id
        }

        $invoice = Invoice::findOrFail($realId);

        DB::transaction(function () use ($invoice) {
            $invoice->delete();
        });

        return redirect()->back()->with('success', 'Invoice voided successfully.');
    }
}