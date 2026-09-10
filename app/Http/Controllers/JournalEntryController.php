<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Models\VoucherType;
use App\Models\Patron;
use App\Models\Plant;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Concerns\AuthorizesModule;

class JournalEntryController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'journal_entry';

    /**
     * Display a listing of journal entries with dependencies.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        // Active non-soft-deleted entries
        $entries = JournalEntry::with(['lines.ledger', 'lines.partner', 'creator', 'plant'])
            ->when($plantId, fn($q) => $q->where('plant_id', $plantId))
            ->whereNull('deleted_at')
            ->where('is_deleted', 0)
            ->latest('id')
            ->get();

        // Ledgers for active plant + global ledgers (where plant_id is null)
        $ledgers = Ledger::when($plantId, fn($q) => $q->where(function ($q2) use ($plantId) {
                $q2->where('plant_id', $plantId)->orWhereNull('plant_id');
            }))
            ->where('status', 1)
            ->orderBy('title', 'asc')
            ->get(['id', 'code', 'title', 'account_type_id', 'plant_id']);

        // Voucher Types are GLOBAL (no plant_id filter)
        $voucherTypes = VoucherType::orderBy('voucher_group', 'asc')
            ->orderBy('journal_name', 'asc')
            ->get(['id', 'journal_name', 'short_code', 'prefix', 'voucher_group', 'is_system_generated']);

        // Patrons / Customers / Vendors
        $partners = Patron::withoutGlobalScopes()
            ->when($plantId, fn($q) => $q->where(function ($q2) use ($plantId) {
                $q2->where('plant_id', $plantId)->orWhereNull('plant_id');
            }))
            ->whereNull('deleted_at')
            ->orderBy('legal_name', 'asc')
            ->get(['id', 'legal_name', 'code', 'patron_type']);

        // Pre-calculate next global voucher numbers for each voucher type (Gap filling)
        $nextVoucherNumbers = [];
        foreach ($voucherTypes as $vt) {
            $nextVoucherNumbers[$vt->short_code] = JournalEntry::generateNextVoucherNumber($vt->short_code);
        }

        $firstVType = $voucherTypes->first()?->short_code ?? 'JV';
        $initialVoucherNumber = $nextVoucherNumbers[$firstVType] ?? JournalEntry::generateNextVoucherNumber($firstVType);

        return Inertia::render('JournalEntry/Index', [
            'entries'              => $entries,
            'ledgers'              => $ledgers,
            'voucherTypes'         => $voucherTypes,
            'partners'             => $partners,
            'nextVoucherNumbers'   => $nextVoucherNumbers,
            'initialVoucherNumber' => $initialVoucherNumber,
            'initialVoucherType'   => $firstVType,
        ]);
    }

    /**
     * Store a newly created journal entry (Double-Entry).
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');
        $entityId = session('active_entity_id') ?: ($plantId ? Plant::find($plantId)?->entity_id : null);
        $userId = Auth::id();

        $validated = $request->validate([
            'voucher_type'           => ['required', 'string'],
            'voucher_number'         => ['nullable', 'string', 'max:50'],
            'voucher_date'           => ['required', 'date'],
            'posting_date'           => ['required', 'date'],
            'narration'              => ['nullable', 'string'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', 'exists:mm_ledgers,id'],
            'lines.*.debit_amount'   => ['required', 'numeric', 'min:0'],
            'lines.*.credit_amount'  => ['required', 'numeric', 'min:0'],
            'lines.*.partner_id'     => ['nullable', 'exists:mm_patrons,id'],
            'lines.*.line_narration' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated, $plantId, $entityId, $userId, $request) {
            $totalDebit = (float) collect($validated['lines'])->sum('debit_amount');
            $totalCredit = (float) collect($validated['lines'])->sum('credit_amount');

            // 1. Balance Check
            if (abs($totalDebit - $totalCredit) > 0.0001) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'The journal must be balanced. Total Debit must equal Total Credit.',
                        'errors'  => ['lines' => 'Debits ₹' . number_format($totalDebit, 2) . ' != Credits ₹' . number_format($totalCredit, 2)]
                    ], 422);
                }
                return redirect()->back()->withErrors(['lines' => 'The journal must be balanced. Total Debit must equal Total Credit.']);
            }

            if ($totalDebit <= 0) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Total journal amount must be greater than zero.',
                        'errors'  => ['lines' => 'Amounts cannot be zero.']
                    ], 422);
                }
                return redirect()->back()->withErrors(['lines' => 'Total journal amount must be greater than zero.']);
            }

            // 2. Voucher Number Generation (Global sequence with gap-filling)
            $voucherNumber = !empty($validated['voucher_number']) 
                ? trim($validated['voucher_number'])
                : JournalEntry::generateNextVoucherNumber($validated['voucher_type']);

            // Check if active voucher number conflict exists
            $existingActive = JournalEntry::where('voucher_number', $voucherNumber)
                ->where('voucher_type', $validated['voucher_type'])
                ->whereNull('deleted_at')
                ->where('is_deleted', 0)
                ->exists();

            if ($existingActive) {
                // Generate a fresh unique gap-filling sequence number
                $voucherNumber = JournalEntry::generateNextVoucherNumber($validated['voucher_type']);
            }

            // 3. Create Header Record
            $entry = JournalEntry::create([
                'entity_id'       => $entityId,
                'plant_id'        => $plantId,
                'voucher_type'    => $validated['voucher_type'],
                'voucher_number'  => $voucherNumber,
                'voucher_date'    => $validated['voucher_date'],
                'posting_date'    => $validated['posting_date'],
                'narration'       => $validated['narration'] ?? null,
                'narration_label' => JournalEntry::resolveNarrationLabel(null, $validated['voucher_type']),
                'total_debit'     => $totalDebit,
                'total_credit'    => $totalCredit,
                'is_status'       => 'POSTED',
                'created_by'      => $userId,
            ]);

            // 4. Create Lines Records
            foreach ($validated['lines'] as $line) {
                $dr = (float) ($line['debit_amount'] ?? 0);
                $cr = (float) ($line['credit_amount'] ?? 0);

                if ($dr <= 0 && $cr <= 0) {
                    continue; // Skip zero amount line
                }

                if ($dr > 0 && $cr > 0) {
                    throw new \Exception('A single line cannot have both debit and credit amounts.');
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'plant_id'         => $plantId,
                    'account_id'       => $line['account_id'],
                    'partner_id'       => $line['partner_id'] ?? null,
                    'debit_amount'     => $dr,
                    'credit_amount'    => $cr,
                    'line_narration'   => $line['line_narration'] ?? null,
                    'narration_label'  => $entry->narration_label,
                    'created_by'       => $userId,
                ]);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Journal Entry Created: ' . $voucherNumber,
                    'entry'   => $entry->load(['lines.ledger', 'lines.partner', 'creator', 'plant'])
                ], 201);
            }

            return redirect()->route('journalentries.index')->with('success', 'Journal Entry Created: ' . $voucherNumber);
        });
    }

    /**
     * Display the specified entry.
     */
    public function show($id)
    {
        $plantId = session('active_plant_id');
        $entry = JournalEntry::with(['lines.ledger', 'lines.partner', 'creator', 'plant'])
            ->when($plantId, fn($q) => $q->where('plant_id', $plantId))
            ->findOrFail($id);

        return response()->json($entry);
    }

    /**
     * Update the specified journal entry.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeModule('edit');
        $plantId = session('active_plant_id');
        $userId = Auth::id();

        $entry = JournalEntry::when($plantId, fn($q) => $q->where('plant_id', $plantId))
            ->findOrFail($id);

        $validated = $request->validate([
            'voucher_date'           => ['required', 'date'],
            'posting_date'           => ['required', 'date'],
            'narration'              => ['nullable', 'string'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', 'exists:mm_ledgers,id'],
            'lines.*.debit_amount'   => ['required', 'numeric', 'min:0'],
            'lines.*.credit_amount'  => ['required', 'numeric', 'min:0'],
            'lines.*.partner_id'     => ['nullable', 'exists:mm_patrons,id'],
            'lines.*.line_narration' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated, $entry, $userId, $request) {
            $totalDebit = (float) collect($validated['lines'])->sum('debit_amount');
            $totalCredit = (float) collect($validated['lines'])->sum('credit_amount');

            // 1. Balance Check
            if (abs($totalDebit - $totalCredit) > 0.0001) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'The journal must be balanced. Total Debit must equal Total Credit.',
                        'errors'  => ['lines' => 'Debits ₹' . number_format($totalDebit, 2) . ' != Credits ₹' . number_format($totalCredit, 2)]
                    ], 422);
                }
                return redirect()->back()->withErrors(['lines' => 'The journal must be balanced. Total Debit must equal Total Credit.']);
            }

            if ($totalDebit <= 0) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Total journal amount must be greater than zero.',
                        'errors'  => ['lines' => 'Amounts cannot be zero.']
                    ], 422);
                }
                return redirect()->back()->withErrors(['lines' => 'Total journal amount must be greater than zero.']);
            }

            // 2. Update Header Record
            $entry->update([
                'voucher_date'    => $validated['voucher_date'],
                'posting_date'    => $validated['posting_date'],
                'narration'       => $validated['narration'] ?? null,
                'total_debit'     => $totalDebit,
                'total_credit'    => $totalCredit,
                'updated_by'      => $userId,
            ]);

            // 3. Replace Lines
            $entry->lines()->delete();

            foreach ($validated['lines'] as $line) {
                $dr = (float) ($line['debit_amount'] ?? 0);
                $cr = (float) ($line['credit_amount'] ?? 0);

                if ($dr <= 0 && $cr <= 0) {
                    continue;
                }

                if ($dr > 0 && $cr > 0) {
                    throw new \Exception('A single line cannot have both debit and credit amounts.');
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'plant_id'         => $entry->plant_id,
                    'account_id'       => $line['account_id'],
                    'partner_id'       => $line['partner_id'] ?? null,
                    'debit_amount'     => $dr,
                    'credit_amount'    => $cr,
                    'line_narration'   => $line['line_narration'] ?? null,
                    'narration_label'  => $entry->narration_label,
                    'created_by'       => $userId,
                ]);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Journal Entry Updated: ' . $entry->voucher_number,
                    'entry'   => $entry->fresh(['lines.ledger', 'lines.partner', 'creator', 'plant'])
                ]);
            }

            return redirect()->route('journalentries.index')->with('success', 'Journal Entry Updated: ' . $entry->voucher_number);
        });
    }

    /**
     * Remove the specified entry (Soft Delete).
     * Frees the reference number for re-use.
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeModule('delete');
        $plantId = session('active_plant_id');
        $entry = JournalEntry::when($plantId, fn($q) => $q->where('plant_id', $plantId))->findOrFail($id);

        $voucherNum = $entry->voucher_number;
        $entry->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Journal Entry {$voucherNum} deleted successfully. Reference number is now available for re-use.",
            ]);
        }

        return redirect()->route('journalentries.index')->with('success', "Journal Entry {$voucherNum} deleted successfully. Reference number is now available for re-use.");
    }
}
