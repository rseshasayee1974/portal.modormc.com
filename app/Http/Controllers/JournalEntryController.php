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
use Illuminate\Validation\Rule;
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
            ->latest()
            ->get();

        return Inertia::render('JournalEntry/Index', [
            'entries'      => $entries,
            'ledgers'      => function_exists('LedgersDropdown') ? LedgersDropdown() : Ledger::all(),
            'voucherTypes' => function_exists('VoucherTypesDropdown') ? VoucherTypesDropdown() : VoucherType::all(),
            'partners'     => function_exists('PatronsDropdown') ? PatronsDropdown() : Patron::all(),
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
            'voucher_type'   => ['required', 'string'],
            'voucher_id'     => ['nullable'],
            'voucher_name'   => ['nullable', 'string'],
            'voucher_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('mm_journal_entries', 'voucher_number')
                    ->where(fn ($query) => $query->where('plant_id', $plantId)->where('is_deleted', 0)->whereNull('deleted_at')),
            ],
            'voucher_date'   => ['required', 'date'],
            'posting_date'   => ['required', 'date'],
            'narration'      => ['nullable', 'string'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['nullable', 'required_without:lines.*.partner_id', 'exists:mm_ledgers,id'],
            'lines.*.debit_amount'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit_amount'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.partner_id'     => ['nullable'],
            'lines.*.line_narration' => ['nullable', 'string', 'max:255'],
        ]);

        // Auto-resolve account_id for lines where partner_id is selected without an account and cast amounts
        foreach ($validated['lines'] as $i => &$line) {
            $line['debit_amount'] = (float) ($line['debit_amount'] ?? 0);
            $line['credit_amount'] = (float) ($line['credit_amount'] ?? 0);

            if (empty($line['account_id']) && !empty($line['partner_id'])) {
                $line['account_id'] = JournalEntry::resolvePatronLedgerId(
                    $plantId,
                    $line['partner_id'],
                    $line['debit_amount'],
                    $line['credit_amount']
                );
            }
            if (empty($line['account_id'])) {
                return response()->json([
                    'message' => "The ledger account for line " . ($i + 1) . " could not be resolved. Please select an Account or configure default Patron ledgers in Settings.",
                    'errors'  => ["lines.{$i}.account_id" => ["The ledger account is required."]]
                ], 422);
            }
        }
        unset($line);

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

            // 2. Voucher Number Generation & Duplicate Validation (where deleted_at IS NULL)
            $voucherNumber = !empty($validated['voucher_number'])
                ? $validated['voucher_number']
                : JournalEntry::generateVoucherNumber(
                    $plantId,
                    $validated['voucher_type'],
                    $validated['voucher_id'] ?? null,
                    $validated['voucher_date'] ?? null
                );

            if (JournalEntry::isDuplicateVoucherNumber($plantId, $voucherNumber)) {
                return response()->json([
                    'message' => "Voucher number {$voucherNumber} already exists for this plant.",
                    'errors'  => ['voucher_number' => ["Voucher number {$voucherNumber} already exists."]]
                ], 422);
            }

            // 3. Build Header and Line Narrations
            $narrations = JournalEntry::buildDefaultNarrations(
                $validated['lines'],
                $validated['voucher_type'],
                $voucherNumber,
                $validated['narration'] ?? null
            );

            // 4. Create Header
            $entry = JournalEntry::create([
                'entity_id'       => $entityId,
                'plant_id'        => $plantId,
                'voucher_type'    => $validated['voucher_type'],
                'voucher_number'  => $voucherNumber,
                'ref_module'      => 'entries',
                'voucher_date'    => $validated['voucher_date'],
                'posting_date'    => $validated['posting_date'],
                'narration'       => $narrations['header'],
                'narration_label' => $validated['voucher_type'],
                'total_debit'     => $totalDebit,
                'total_credit'    => $totalCredit,
                'is_status'       => 'POSTED',
                'created_by'      => $userId,
            ]);

            // 5. Create Lines
            foreach ($validated['lines'] as $idx => $line) {
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
                    'plant_id'         => $plantId,
                    'account_id'       => $line['account_id'],
                    'debit_amount'     => $dr,
                    'credit_amount'    => $cr,
                    'partner_type'     => !empty($line['partner_id']) ? 'Patron' : null,
                    'partner_id'       => $line['partner_id'] ?? null,
                    'narration_name'   => 'Journal entry',
                    'narration_label'  => $validated['voucher_type'],
                    'line_narration'   => $narrations['lines'][$idx] ?? null,
                    'created_by'       => $userId,
                ]);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Journal Entry Created: ' . $entry->voucher_number,
                    'entry'   => $entry->fresh(['lines.ledger', 'lines.partner', 'creator', 'plant'])
                ], 201);
            }

            return redirect()->route('journalentries.index')->with('success', 'Journal Entry Created: ' . $entry->voucher_number);
        });
    }

    /**
     * Display the specified entry.
     */
    public function show($id)
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $entry = JournalEntry::with(['lines.ledger', 'lines.partner', 'creator', 'plant'])
            ->when($plantId, fn($q) => $q->where('plant_id', $plantId))
            ->whereNull('deleted_at')
            ->findOrFail($id);

        return response()->json($entry);
    }

    /**
     * Remove the specified entry (Soft Delete).
     * Frees the reference number for re-use.
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeModule('delete');
        $plantId = session('active_plant_id');
        $entry = JournalEntry::where('plant_id', $plantId)->findOrFail($id);
        
        $entry->delete();

        return response()->json([
            'message' => 'Journal Entry Deleted Successfully!',
        ]);
    }

    /**
     * Generate the next voucher number for a journal entry.
     */
    public function generateVoucherNumber(Request $request)
    {
        $plantId = session('active_plant_id') ?? $request->input('plant_id');
        $voucherType = $request->input('voucher_type', 'Journal');
        $voucherId = $request->input('voucher_id');
        $voucherDate = $request->input('voucher_date', now()->toDateString());

        $voucherNumber = JournalEntry::generateVoucherNumber($plantId, $voucherType, $voucherId, $voucherDate);

        return response()->json([
            'voucher_number' => $voucherNumber
        ]);
    }
}