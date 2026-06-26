<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\BankStatementLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\VoucherType;
use App\Services\Finance\BankReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Concerns\AuthorizesModule;

class BankReconciliationController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'ledgers';
    /**
     * Display the Bank Reconciliation index page.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');
        
        // Fetch all ledgers for the dropdown (user selects their bank ledger)
        $ledgers = Ledger::where('plant_id', $plantId)
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        // Also fetch all ledgers for quick voucher offsetting (e.g. Bank Charges)
        $offsetLedgers = Ledger::where('plant_id', $plantId)
            ->where('status', 1)
            ->orderBy('title')
            ->get();

        return Inertia::render('Finance/BankReconciliation', [
            'ledgers' => $ledgers,
            'offsetLedgers' => $offsetLedgers,
        ]);
    }

    /**
     * Upload and import a bank statement CSV.
     */
    public function upload(Request $request, BankReconciliationService $service)
    {
        $this->authorizeModule('create');
        $request->validate([
            'bank_ledger_id' => 'required|exists:mm_ledgers,id',
            'statement_file' => 'required|file|mimes:csv,txt|max:5120', // Max 5MB
        ]);

        $plantId = session('active_plant_id');
        $bankLedgerId = $request->input('bank_ledger_id');
        $file = $request->file('statement_file');

        // Store file temporarily in the workspace
        $tempPath = $file->storeAs('temp_brs', 'brs_' . time() . '_' . $file->getClientOriginalName(), 'local');
        $absolutePath = storage_path('app/' . $tempPath);

        try {
            $importedCount = $service->importStatement($absolutePath, $bankLedgerId, $plantId);
            @unlink($absolutePath); // Clean up
            return redirect()->back()->with('success', "Successfully imported {$importedCount} bank statement lines.");
        } catch (\Exception $e) {
            @unlink($absolutePath); // Clean up
            return redirect()->back()->withErrors(['statement_file' => 'Failed to parse CSV: ' . $e->getMessage()]);
        }
    }

    /**
     * Fetch imported statement lines and their suggested ledger entry matches.
     */
    public function getLines(Request $request, BankReconciliationService $service)
    {
        $this->authorizeModule('show');
        $request->validate([
            'bank_ledger_id' => 'required|exists:mm_ledgers,id',
        ]);

        $bankLedgerId = $request->query('bank_ledger_id');
        $plantId = session('active_plant_id');

        $lines = BankStatementLine::where('bank_ledger_id', $bankLedgerId)
            ->where('plant_id', $plantId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $result = $lines->map(function ($line) use ($service) {
            $suggestions = [];
            $reconciledLine = null;

            if ($line->reconciled_line_id) {
                // Fetch reconciled journal line details
                $jl = JournalEntryLine::with('entry')->find($line->reconciled_line_id);
                if ($jl) {
                    $reconciledLine = [
                        'id' => $jl->id,
                        'voucher_number' => $jl->entry->voucher_number,
                        'voucher_date' => $jl->entry->voucher_date->toDateString(),
                        'narration' => $jl->line_narration ?: $jl->entry->narration,
                        'debit' => (float)$jl->debit_amount,
                        'credit' => (float)$jl->credit_amount,
                    ];
                }
            } else {
                // Only load suggestions if not reconciled yet
                $suggestions = $service->getMatchingSuggestions($line);
            }

            return [
                'id' => $line->id,
                'transaction_date' => $line->transaction_date->toDateString(),
                'description' => $line->description,
                'reference_no' => $line->reference_no,
                'debit' => (float)$line->debit_amount,
                'credit' => (float)$line->credit_amount,
                'balance' => $line->balance !== null ? (float)$line->balance : null,
                'reconciled_line_id' => $line->reconciled_line_id,
                'reconciled_at' => $line->reconciled_at ? $line->reconciled_at->toDateTimeString() : null,
                'reconciled_line' => $reconciledLine,
                'suggestions' => $suggestions,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Link/reconcile a bank statement line with a system journal entry line.
     */
    public function reconcile(Request $request)
    {
        $this->authorizeModule('edit');
        $request->validate([
            'statement_line_id' => 'required|exists:mm_bank_statement_lines,id',
            'journal_line_id' => 'required|exists:mm_journal_entry_lines,id',
        ]);

        $statementLineId = $request->input('statement_line_id');
        $journalLineId = $request->input('journal_line_id');

        return DB::transaction(function () use ($statementLineId, $journalLineId) {
            $statementLine = BankStatementLine::lockForUpdate()->findOrFail($statementLineId);
            $journalLine = JournalEntryLine::lockForUpdate()->findOrFail($journalLineId);

            if ($statementLine->reconciled_line_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Statement line is already reconciled.'
                ], 422);
            }

            if ($journalLine->bank_statement_line_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal entry line is already reconciled.'
                ], 422);
            }

            // Perform link
            $now = now();
            $statementLine->update([
                'reconciled_line_id' => $journalLineId,
                'reconciled_at' => $now,
            ]);

            $journalLine->update([
                'bank_statement_line_id' => $statementLineId,
                'reconciled_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction successfully reconciled.'
            ]);
        });
    }

    /**
     * Unlink/unreconcile a bank statement line.
     */
    public function unreconcile(Request $request)
    {
        $this->authorizeModule('edit');
        $request->validate([
            'statement_line_id' => 'required|exists:mm_bank_statement_lines,id',
        ]);

        $statementLineId = $request->input('statement_line_id');

        return DB::transaction(function () use ($statementLineId) {
            $statementLine = BankStatementLine::lockForUpdate()->findOrFail($statementLineId);

            if (!$statementLine->reconciled_line_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Statement line is not reconciled.'
                ], 422);
            }

            $journalLineId = $statementLine->reconciled_line_id;

            // Unlink journal entry line
            JournalEntryLine::where('id', $journalLineId)->update([
                'bank_statement_line_id' => null,
                'reconciled_at' => null,
            ]);

            // Unlink statement line
            $statementLine->update([
                'reconciled_line_id' => null,
                'reconciled_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reconciliation link removed.'
            ]);
        });
    }

    /**
     * Instantly create a journal entry voucher to match an unaccounted bank transaction.
     */
    public function createVoucher(Request $request)
    {
        $this->authorizeModule('create');
        $request->validate([
            'statement_line_id' => 'required|exists:mm_bank_statement_lines,id',
            'opposite_ledger_id' => 'required|exists:mm_ledgers,id',
            'narration' => 'nullable|string|max:255',
        ]);

        $statementLineId = $request->input('statement_line_id');
        $oppositeLedgerId = $request->input('opposite_ledger_id');
        $customNarration = $request->input('narration');

        $plantId = session('active_plant_id');
        $plant = DB::table('mm_plants')->find($plantId);
        $entityId = $plant ? $plant->entity_id : 1;
        $userId = Auth::id() ?? 1;

        return DB::transaction(function () use ($statementLineId, $oppositeLedgerId, $customNarration, $plantId, $entityId, $userId) {
            $statementLine = BankStatementLine::lockForUpdate()->findOrFail($statementLineId);

            if ($statementLine->reconciled_line_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Statement line is already reconciled.'
                ], 422);
            }

            $isWithdrawal = $statementLine->debit_amount > 0;
            $amount = $isWithdrawal ? $statementLine->debit_amount : $statementLine->credit_amount;

            // 1. Determine Voucher Prefix
            $vType = VoucherType::where('short_code', 'JOURNAL')->first();
            $prefix = $vType ? $vType->prefix : 'JV-';

            $lastEntry = JournalEntry::where('entity_id', $entityId)
                ->where('voucher_type', 'JOURNAL')
                ->orderBy('id', 'desc')
                ->first();
            
            $nextNum = $lastEntry ? (int) filter_var($lastEntry->voucher_number, FILTER_SANITIZE_NUMBER_INT) + 1 : 1;
            $voucherNumber = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

            // 2. Create Header
            $narrationText = $customNarration ?: "BRS Quick Entry: " . $statementLine->description;

            $journalEntry = JournalEntry::create([
                'entity_id'      => $entityId,
                'plant_id'       => $plantId,
                'voucher_type'   => 'JOURNAL',
                'voucher_number' => $voucherNumber,
                'voucher_date'   => $statementLine->transaction_date,
                'posting_date'   => $statementLine->transaction_date,
                'narration'      => $narrationText,
                'total_debit'    => $amount,
                'total_credit'   => $amount,
                'is_status'      => 'POSTED',
                'created_by'     => $userId,
            ]);

            // 3. Create Bank Line (Auto-Reconciled)
            $bankLine = JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'plant_id' => $plantId,
                'account_id' => $statementLine->bank_ledger_id,
                'debit_amount' => $isWithdrawal ? 0.0000 : $amount, // Deposit is Debit to bank account ledger
                'credit_amount' => $isWithdrawal ? $amount : 0.0000, // Withdrawal is Credit from bank account ledger
                'line_narration' => $statementLine->description,
                'bank_statement_line_id' => $statementLine->id,
                'reconciled_at' => now(),
                'created_by' => $userId,
            ]);

            // 4. Create Offsetting Line
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'plant_id' => $plantId,
                'account_id' => $oppositeLedgerId,
                'debit_amount' => $isWithdrawal ? $amount : 0.0000, // Withdrawal represents Expense/Asset/Liability Debit
                'credit_amount' => $isWithdrawal ? 0.0000 : $amount, // Deposit represents Income/Liability Credit
                'line_narration' => $statementLine->description,
                'created_by' => $userId,
            ]);

            // 5. Update Statement Line
            $statementLine->update([
                'reconciled_line_id' => $bankLine->id,
                'reconciled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Journal Entry {$voucherNumber} created and reconciled successfully."
            ]);
        });
    }
}
