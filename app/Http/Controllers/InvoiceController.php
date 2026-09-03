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
    protected string $module = 'invoice';

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
            'taxes'   => collect(TaxesDropdown('sales',['GST','IGST']))->map(fn($t) => [
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
            
            // Strictly enforce prefix from verified ledger configuration, preventing any client-side tampering
            $details = Invoice::generateNumber($plantId, $validated['invoice_type'] ?? 'sales', $validated['account_id'] ?? null);
            $validated['prefix'] = $details['prefix'];

            // Auto-generate numbering if not provided
            if (empty($validated['invoice_number'])) {
                $validated['invoice_number'] = $details['next_number'];
            } else {
                if (str_starts_with((string)$validated['invoice_number'], $validated['prefix'])) {
                    $validated['invoice_number'] = substr($validated['invoice_number'], strlen($validated['prefix']));
                }
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

    public function checkInvoiceNumber(Request $request)
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $rawNumber = trim((string)$request->query('invoice_number', ''));
        $accountId = $request->query('account_id');
        $type = $request->query('invoice_type', 'sales');
        $excludeId = $request->query('exclude_id');

        // Always strictly determine prefix from the ledger and plant configuration, never trust client input
        $gen = Invoice::generateNumber($plantId, $type, $accountId ? (int)$accountId : null);
        $prefix = $gen['prefix'];

        if ($rawNumber === '') {
            return response()->json([
                'exists' => false,
                'available' => true,
                'message' => '',
                'full_number' => '',
                'prefix' => $prefix,
            ]);
        }

        // Handle case where user entered the full number with prefix
        if (!empty($prefix) && str_starts_with($rawNumber, $prefix)) {
            $numOnly = substr($rawNumber, strlen($prefix));
            $fullNumber = $rawNumber;
        } else {
            $numOnly = $rawNumber;
            $fullNumber = (!empty($prefix) ? $prefix : '') . $rawNumber;
        }

        $exists = Invoice::withoutGlobalScopes()
            ->where('plant_id', $plantId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($prefix, $numOnly, $fullNumber, $rawNumber) {
                $q->where(function ($sub) use ($prefix, $numOnly) {
                    if (!empty($prefix)) {
                        $sub->where('prefix', $prefix)
                            ->where('invoice_number', $numOnly);
                    } else {
                        $sub->where('invoice_number', $numOnly);
                    }
                })
                ->orWhere('invoice_number', $rawNumber)
                ->orWhere('invoice_number', $fullNumber)
                ->orWhere(DB::raw("CONCAT(COALESCE(prefix, ''), invoice_number)"), $fullNumber)
                ->orWhere(DB::raw("CONCAT(COALESCE(prefix, ''), invoice_number)"), $rawNumber);
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        return response()->json([
            'exists' => $exists,
            'available' => !$exists,
            'full_number' => $fullNumber,
            'message' => $exists
                ? "Invoice #{$fullNumber} already exists in this plant. Duplicate not allowed."
                : "Invoice #{$fullNumber} is available.",
        ]);
    }

    public function getNextNumber(Request $request)
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');
        $accountId = $request->query('account_id');
        $type = $request->query('invoice_type', 'sales');

        $details = Invoice::generateNumber($plantId, $type, $accountId ? (int)$accountId : null);

        return response()->json($details);
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