<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 17:51:51 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Invoice;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvoiceNoteController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'invoice';

    public function index()
    {
        $this->authorizeModule('menu', 'crdrnote');
        $plantId = session('active_plant_id');
        return Inertia::render('CrDrNote/Index', [
            'purchaseLedgers' => Ledger::noteAccounts($plantId, 'debit_note')->orderBy('title')->get(['id as value', 'title as label']),
            'salesLedgers' => Ledger::noteAccounts($plantId, 'credit_note')->orderBy('title')->get(['id as value', 'title as label']),
            'notes' => Invoice::where('plant_id', $plantId)->whereIn('invoice_type', ['credit_note', 'debit_note'])
                ->with(['partner:id,legal_name', 'account:id,title', 'sourceDocument', 'noteEinvoiceRelation', 'einvoiceRelation', 'items.uom', 'items.tax'])->latest()->get(),
            'documents' => Invoice::where('plant_id', $plantId)
                ->whereIn('invoice_type', array_merge(\App\Support\InvoiceClassification::aliases('Invoice'), \App\Support\InvoiceClassification::aliases('Bill')))
                ->whereRaw('LOWER(status) IN (?, ?)', ['approved', 'paid'])
                ->with(['partner:id,legal_name', 'account:id,title', 'items.uom', 'items.tax', 'adjustmentNotes'])->latest()->get(),
            'taxes' => \App\Models\Tax::where('plant_id', $plantId)->whereNull('parent_id')
                ->where('status', 1)->whereIn('tax_type', ['sales', 'purchase'])
                ->whereIn('tax_group', ['GST', 'IGST'])->get(['id', 'tax_name', 'tax_rate', 'tax_group', 'tax_type']),
            'units' => \App\Models\ProductUnit::forDropdown()->get(['id', 'unit_code']),
            'products' => ProductsDropdown('purchase'),
            'mixdesign' => MixDesignsOptions(),
            'note_number_details' => [
                'credit_note' => Invoice::generateNumber($plantId, 'credit_note'),
                'debit_note' => Invoice::generateNumber($plantId, 'debit_note'),
            ],
        ]);
    }

    private function editNumber(Invoice $note, ?int $accountId, ?string $requestedNumber): array
    {
        $accountId = $accountId ?? $note->account_id;
        $ledgerChanged = (int) $accountId !== (int) $note->account_id;
        $seriesChanged = $ledgerChanged
            && Invoice::generateNumber($note->plant_id, $note->invoice_type, $accountId)['prefix'] !== $note->prefix;
        $storedSequence = preg_replace('~/\d{2}-\d{2}$~', '', (string) $note->invoice_number);
        $requestedNumber = trim((string) $requestedNumber);
        $numberChanged = $requestedNumber !== '' && !in_array($requestedNumber, [$storedSequence, $note->invoice_number, $note->full_number], true);
        if ($seriesChanged || $numberChanged) {
            return Invoice::adjustmentNoteNumber($note->plant_id, $note->invoice_type, $numberChanged ? $requestedNumber : null, $note->id, $accountId);
        }

        return $note->only(['prefix', 'invoice_number']) + ['full_number' => $note->full_number];
    }

    public function previewNumber(Request $request)
    {
        $this->authorizeModule($request->filled('note_id') ? 'edit' : 'create', 'crdrnote');
        $data = $request->validate([
            'note_type' => 'required|in:credit_note,debit_note',
            'note_id' => 'nullable|integer',
            'account_id' => 'nullable|integer|min:1',
            'invoice_number' => 'nullable|string|max:100',
        ]);
        $plantId = session('active_plant_id');
        $accountId = $data['account_id'] ?? null;
        $number = $data['invoice_number'] ?? null;
        if (!empty($data['note_id'])) {
            $note = Invoice::where('plant_id', $plantId)->where('invoice_type', $data['note_type'])->findOrFail($data['note_id']);
            $details = $this->editNumber($note, $accountId, $number);
        } else {
            $details = Invoice::adjustmentNoteNumber($plantId, $data['note_type'], $number, accountId: $accountId);
        }

        return response()->json($details)->header('Cache-Control', 'no-store');
    }

    public function moduleStore(Request $request)
    {
        $this->authorizeModule('create', 'crdrnote');
        $request->validate(['source_id' => 'required|integer']);
        $source = Invoice::where('plant_id', session('active_plant_id'))->findOrFail($request->input('source_id'));
        $data = $this->validateNote($request, $source);
        $note = $source->generateAdjustmentNote($data['note_type'], $data['note_date'], $data['reason'], $data);
        return redirect()->route('crdrnote.index')->with('success', $note->invoice_label . ' ' . $note->full_number . ' created.');
    }

    public function update(Request $request, Invoice $note)
    {
        $this->authorizeModule('edit', 'crdrnote');
        abort_unless((int) $note->plant_id === (int) session('active_plant_id')
            && in_array($note->invoice_type, ['credit_note', 'debit_note'], true), 404);
        $source = $note->sourceDocument;
        abort_unless($source && (int) $source->plant_id === (int) $note->plant_id, 404);
        $data = $request->validate(array_merge([
            'note_date' => 'required|date|after_or_equal:' . $source->invoice_date->toDateString(),
            'reason' => 'required|string|max:2000',
            'invoice_number' => 'sometimes|required|string|max:100',
        ], $this->ledgerRules($note, $note->invoice_type), $this->itemRules($note)), [
            'items.*.tax_id.exists' => 'Select an active GST/IGST tax from this plant: Sales tax for a credit note or Purchase tax for a debit note.',
        ]);
        $this->validateDiscounts($data);
        DB::transaction(function () use ($note, $data) {
            $note = Invoice::whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->einvoice_irn) {
                throw \Illuminate\Validation\ValidationException::withMessages(['note' => 'This note has a registered IRN and cannot be modified.']);
            }
            if (strtolower((string) $note->status) !== 'approved') {
                throw \Illuminate\Validation\ValidationException::withMessages(['reason' => 'Only approved notes can be edited.']);
            }
            \App\Models\Plant::withoutGlobalScopes()->whereKey($note->plant_id)->lockForUpdate()->firstOrFail();
            $number = $this->editNumber($note, $data['account_id'] ?? null, $data['invoice_number'] ?? null);
            $numberFields = ['prefix' => $number['prefix'], 'invoice_number' => $number['invoice_number']];
            $oldNumber = $note->full_number;
            $note->update(['account_id' => $data['account_id'] ?? $note->account_id, 'invoice_date' => $data['note_date'], 'due_date' => $data['note_date'],
                'notes' => $data['reason'], 'updated_by' => auth()->id()] + $numberFields);
            if ($numberFields && $oldNumber !== $note->full_number) {
                \App\Models\JournalEntry::where('plant_id', $note->plant_id)
                    ->where('ref_id', $note->id)->where('voucher_type', strtoupper($note->invoice_type))
                    ->where('voucher_number', $oldNumber)->update(['voucher_number' => $note->full_number]);
            }
            if (isset($data['items'])) {
                $note->updateWithItems(['items' => $data['items'], 'is_tax_inclusive' => $data['is_tax_inclusive'] ?? $note->is_tax_inclusive]);
                if ((float) $note->total_amount <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'The note amount must be positive.']);
                }
            }
            $note->postToAccounting();
        });
        return redirect()->route('crdrnote.index')->with('success', 'Note updated successfully.');
    }

    private function ledgerRules(Invoice $document, $type): array
    {
        if (!in_array($type, ['credit_note', 'debit_note'], true)) return [];
        return ['account_id' => ['bail', 'nullable', 'integer', 'min:1', function ($attribute, $value, $fail) use ($document, $type) {
            if ((int) $value !== (int) $document->account_id
                && !Ledger::noteAccounts($document->plant_id, $document->accountingModule() === 'Purchase' ? 'debit_note' : 'credit_note')->whereKey($value)->exists()) {
                $fail('Select a ledger from the appropriate account group for this plant.');
            }
        }]];
    }

    private function validateNote(Request $request, Invoice $source): array
    {
        $data = $request->validate(array_merge([
            'note_type' => 'required|in:credit_note,debit_note',
            'debit_source' => 'nullable|in:sales,purchase',
            'invoice_number' => 'nullable|string|max:100',
            'note_date' => 'required|date|after_or_equal:' . $source->invoice_date->toDateString(),
            'reason' => 'required|string|max:2000',
        ], $this->ledgerRules($source, $request->input('note_type')), $this->itemRules($source)), [
            'items.*.tax_id.exists' => 'Select an active GST/IGST tax from this plant: Sales tax for a credit note or Purchase tax for a debit note.',
        ]);
        $this->validateDiscounts($data);
        return $data;
    }

    public function destroy(Invoice $note)
    {
        $this->authorizeModule('delete', 'crdrnote');
        abort_unless(!$note->trashed() && (int) $note->plant_id === (int) session('active_plant_id')
            && in_array($note->invoice_type, ['credit_note', 'debit_note'], true), 404);
        DB::transaction(function () use ($note) {
            $note = Invoice::whereKey($note->id)->lockForUpdate()->firstOrFail();
            if ($note->einvoice_irn) {
                throw \Illuminate\Validation\ValidationException::withMessages(['note' => 'This note has a registered IRN and cannot be modified.']);
            }
            if ((float) $note->paid_amount > 0 || \App\Models\PaymentAllocation::where('invoice_id', $note->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['delete' => 'Reverse the payments allocated to this note before deleting it.']);
            }
            $note->delete();
        });
        return redirect()->route('crdrnote.index')->with('success', 'Note deleted and its accounting entries reverted.');
    }

    private function itemRules(Invoice $owner): array
    {
        $sourceType = $this->itemDocumentType($owner);
        $taxType = in_array($sourceType, ['bill', 'purchase', 'debit_note'], true) ? 'purchase' : 'sales';
        return [
            'is_tax_inclusive' => 'required_with:items|boolean',
            'items' => 'sometimes|required|array|min:1',
            'items.*' => 'required|array:id,item_id,item_name,hsn_code,uom_id,quantity,price_unit,discount_type,discount,tax_id',
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('mm_invoice_items', 'id')->where('invoice_id', $owner->id)->whereNull('deleted_at')],
            'items.*.item_id' => ['nullable', 'integer', function ($attribute, $value, $fail) use ($owner) {
                $index = explode('.', $attribute)[1];
                $original = $owner->items()->find(request()->input("items.$index.id"));
                if ($original && (int) $original->item_id === (int) $value) {
                    return;
                }
                $sourceType = $this->itemDocumentType($owner);
                $isBill = in_array($sourceType, ['bill', 'purchase', 'debit_note'], true);
                $query = $isBill ? \App\Models\Product::query() : \App\Models\MixDesign::query();
                $query->where('plant_id', $owner->plant_id);
                if ($isBill) {
                    $query->where('product_type', 'purchase')->where('status', true);
                }
                if (!$query->whereKey($value)->exists()) {
                    $fail('Select a valid product or mix design from the active factory.');
                }
            }],
            'items.*.item_name' => 'required|string|max:255',
            'items.*.hsn_code' => 'nullable|string|max:10',
            'items.*.uom_id' => ['nullable', 'integer', Rule::exists('mm_product_units', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.price_unit' => 'required|numeric|min:0',
            'items.*.discount_type' => 'required|in:%,₹',
            'items.*.discount' => 'required|numeric|min:0',
            'items.*.tax_id' => ['nullable', 'integer', Rule::exists('mm_taxes', 'id')->where('plant_id', $owner->plant_id)->where('tax_type', $taxType)->where('status', 1)->whereNull('deleted_at')->whereNull('parent_id')->whereIn('tax_group', ['GST', 'IGST'])],
        ];
    }

    private function itemDocumentType(Invoice $owner): string
    {
        $type = strtolower((string) $owner->invoice_type);
        // Only notes reference another invoice; ordinary invoices reference their source module.
        return in_array($type, ['credit_note', 'debit_note'], true)
            ? strtolower((string) ($owner->sourceDocument?->invoice_type ?? $type))
            : $type;
    }

    private function validateDiscounts(array $data): void
    {
        foreach ($data['items'] ?? [] as $index => $item) {
            $limit = $item['discount_type'] === '%' ? 100 : (float) $item['quantity'] * (float) $item['price_unit'];
            if ((float) $item['discount'] > $limit) {
                throw \Illuminate\Validation\ValidationException::withMessages(["items.$index.discount" => 'Discount cannot exceed the line amount.']);
            }
        }
    }

    public function store(Request $request, Invoice $invoice)
    {
        abort_unless((int) $invoice->plant_id === (int) session('active_plant_id'), 404);
        $this->authorizeModule('create', $invoice->accountingModule() === 'Purchase' ? 'billing' : 'invoice');
        $data = $this->validateNote($request, $invoice);
        $note = $invoice->generateAdjustmentNote($data['note_type'], $data['note_date'], $data['reason'], $data);
        return back()->with('success', $note->invoice_label . ' ' . $note->full_number . ' generated against ' . $invoice->full_number);
    }
}
