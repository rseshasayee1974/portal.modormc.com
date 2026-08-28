<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Models\VoucherType;
use App\Models\Entity;
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
     * Display a listing of journal entries.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $entries = JournalEntry::with(['lines.ledger', 'creator'])
            ->where('plant_id', $plantId)
            ->where('is_deleted', 0)
            ->latest()
            ->get();

        $ledgers = Ledger::where('plant_id', $plantId)
            ->where('status', 1)
            ->orderBy('title', 'asc')
            ->get();

        $voucherTypes = VoucherType::where(function ($q) use ($plantId) {
                $q->whereNull('plant_id')
                  ->orWhere('plant_id', $plantId);
            })
            ->get();

        // Using entities as 'Patrons' or 'Partners' for now
        $partners = Entity::orderBy('legal_name', 'asc')->get();

        return Inertia::render('JournalEntry/Index', [
            'entries'      => $entries,
            'ledgers'      => $ledgers,
            'voucherTypes' => $voucherTypes,
            'partners'     => $partners,
        ]);
    }

    /**
     * Store a newly created journal entry.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');
        $userId = Auth::id();

        $validated = $request->validate([
            'voucher_type'   => ['required', 'string'],
            'voucher_date'   => ['required', 'date'],
            'posting_date'   => ['required', 'date'],
            'narration'      => ['nullable', 'string'],
            'lines'          => ['required', 'array', 'min:2'],
            'lines.*.account_id'    => ['required', 'exists:mm_ledgers,id'],
            'lines.*.debit_amount'  => ['required', 'numeric', 'min:0'],
            'lines.*.credit_amount' => ['required', 'numeric', 'min:0'],
            'lines.*.partner_id'    => ['nullable', 'exists:mm_entities,id'],
            'lines.*.line_narration' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated, $plantId, $userId) {
            $totalDebit = collect($validated['lines'])->sum('debit_amount');
            $totalCredit = collect($validated['lines'])->sum('credit_amount');

            // 1. Balance Check
            if (number_format($totalDebit, 4) !== number_format($totalCredit, 4)) {
                return response()->json([
                    'message' => 'The journal must be balanced. Total Debit must equal Total Credit.',
                    'errors'  => ['lines' => 'Debits ' . $totalDebit . ' != Credits ' . $totalCredit]
                ], 422);
            }

            // 2. Voucher Number Generation
            $vType = VoucherType::where('short_code', $validated['voucher_type'])->first();
            $prefix = $vType ? $vType->prefix : ($validated['voucher_type'] . '-');
            
            $lastEntry = JournalEntry::where('plant_id', $plantId)
                ->where('voucher_type', $validated['voucher_type'])
                ->orderBy('id', 'desc')
                ->first();
            
            $nextNum = $lastEntry ? (int) filter_var($lastEntry->voucher_number, FILTER_SANITIZE_NUMBER_INT) + 1 : 1;
            $voucherNumber = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

            // 3. Create Header
            $entry = JournalEntry::create([
                'plant_id'       => $plantId,
                'voucher_type'   => $validated['voucher_type'],
                'voucher_number' => $voucherNumber,
                'voucher_date'   => $validated['voucher_date'],
                'posting_date'   => $validated['posting_date'],
                'narration'      => $validated['narration'],
                'total_debit'    => $totalDebit,
                'total_credit'   => $totalCredit,
                'is_status'      => 'POSTED', // Default to posted for simplicity or 'DRAFT'
                'created_by'     => $userId,
            ]);

            // 4. Create Lines
            foreach ($validated['lines'] as $line) {
                // Ensure exactly one side is populated
                if ($line['debit_amount'] > 0 && $line['credit_amount'] > 0) {
                     throw new \Exception('A single line cannot have both debit and credit amounts.');
                }

                JournalEntryLine::create(array_merge($line, [
                    'journal_entry_id' => $entry->id,
                    'plant_id'         => $plantId,
                    'created_by'       => $userId,
                ]));
            }

            return response()->json([
                'message' => 'Journal Entry Created: ' . $voucherNumber,
                'entry'   => $entry->load('lines.ledger')
            ], 201);
        });
    }

    /**
     * Remove the specified entry.
     */
    public function destroy($id)
    {
        $this->authorizeModule('delete');
        $plantId = session('active_plant_id');
        $entry = JournalEntry::where('plant_id', $plantId)->findOrFail($id);
        
        // Handle logic for posted entries if needed (maybe only allow deletion if DRAFT)
        
        $entry->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => Auth::id()]);

        return response()->json([
            'message' => 'Journal Entry Deleted Successfully!',
        ]);
    }
}
