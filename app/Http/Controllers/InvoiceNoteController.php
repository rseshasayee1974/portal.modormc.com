<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 17:51:51 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'notes' => Invoice::where('plant_id', $plantId)->whereIn('invoice_type', ['credit_note', 'debit_note'])
                ->with(['partner:id,legal_name', 'sourceDocument', 'items.uom'])->latest()->get(),
            'documents' => Invoice::where('plant_id', $plantId)
                ->whereIn('invoice_type', array_merge(\App\Support\InvoiceClassification::aliases('Invoice'), \App\Support\InvoiceClassification::aliases('Bill')))
                ->whereRaw('LOWER(status) IN (?, ?)', ['approved', 'paid'])
                ->with(['partner:id,legal_name', 'items.uom', 'adjustmentNotes'])->latest()->get(),
        ]);
    }

    public function moduleStore(Request $request)
    {
        $this->authorizeModule('create', 'crdrnote');
        $request->validate(['source_id' => 'required|integer']);
        $source = Invoice::where('plant_id', session('active_plant_id'))->findOrFail($request->input('source_id'));
        $data = $this->validateNote($request, $source);
        $note = $source->generateAdjustmentNote($data['note_type'], $data['note_date'], $data['reason']);
        return redirect()->route('crdrnote.index')->with('success', $note->invoice_label . ' ' . $note->full_number . ' created.');
    }

    public function update(Request $request, Invoice $note)
    {
        $this->authorizeModule('edit', 'crdrnote');
        abort_unless((int) $note->plant_id === (int) session('active_plant_id')
            && in_array($note->invoice_type, ['credit_note', 'debit_note'], true), 404);
        $source = $note->sourceDocument;
        abort_unless($source && (int) $source->plant_id === (int) $note->plant_id, 404);
        $data = $request->validate([
            'note_date' => 'required|date|after_or_equal:' . $source->invoice_date->toDateString(),
            'reason' => 'required|string|max:2000',
        ]);
        DB::transaction(function () use ($note, $data) {
            $note = Invoice::whereKey($note->id)->lockForUpdate()->firstOrFail();
            if (strtolower((string) $note->status) !== 'approved') {
                throw \Illuminate\Validation\ValidationException::withMessages(['reason' => 'Only approved notes can be edited.']);
            }
            $note->update(['invoice_date' => $data['note_date'], 'due_date' => $data['note_date'],
                'notes' => $data['reason'], 'updated_by' => auth()->id()]);
            $note->postToAccounting();
        });
        return redirect()->route('crdrnote.index')->with('success', 'Note updated successfully.');
    }

    private function validateNote(Request $request, Invoice $source): array
    {
        return $request->validate([
            'note_type' => 'required|in:credit_note,debit_note',
            'note_date' => 'required|date|after_or_equal:' . $source->invoice_date->toDateString(),
            'reason' => 'required|string|max:2000',
        ]);
    }

    public function store(Request $request, Invoice $invoice)
    {
        abort_unless((int) $invoice->plant_id === (int) session('active_plant_id'), 404);
        $this->authorizeModule('create', $invoice->accountingModule() === 'Purchase' ? 'billing' : 'invoice');
        $data = $this->validateNote($request, $invoice);
        $note = $invoice->generateAdjustmentNote($data['note_type'], $data['note_date'], $data['reason']);
        return back()->with('success', $note->invoice_label . ' ' . $note->full_number . ' generated against ' . $invoice->full_number);
    }
}
