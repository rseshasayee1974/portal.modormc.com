<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

use Illuminate\Support\Facades\DB;
use App\Models\PaymentTransaction;

use Illuminate\Support\Arr;
use App\Models\PaymentAllocation;
use App\Models\Invoice;
use App\Models\PaymentAudit;
use App\Models\PaymentTransactionAudit;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'payments';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('Payments/Index', [
            'payments' => Payment::with(['ledger:id,title', 'patron:id,legal_name', 'creator:id,username'])
                ->where('plant_id', $plantId)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'transaction_date'  => 'required|date',
            'ledger_id'         => 'required|exists:mm_ledgers,id',
            'patron_id'         => 'nullable|exists:mm_patrons,id',
            'partner_type'      => 'nullable|string',
            'origin'            => 'nullable|string',
            'origin_id'         => 'nullable|integer',
            'amount'            => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $useExcess = filter_var($request->input('use_excess_amount'), FILTER_VALIDATE_BOOLEAN);
                    if (!$useExcess && $value < 0.01) {
                        $fail('The amount field must be at least 0.01 when not using previous advance.');
                    } elseif ($value < 0) {
                        $fail('The amount field cannot be negative.');
                    }
                }
            ],
            'excess_amount'     => 'nullable|numeric|min:0',
            'use_excess_amount' => 'nullable|boolean',
            // 'transaction_type'  => 'required|in:payment,receipt',
            'transaction_type'  => ['required',Rule::in(['payment','receipt','Payment','Receipt'])],
            'transaction_mode'  => 'nullable|string',
            'reconcile_opening_balance' => 'nullable|boolean',
            'batch_deposit'     => 'nullable|boolean',
            'description'       => 'nullable|string',
            'reference'         => 'nullable|string|max:100',
            // 'status'            => 'required|in:pending,paid,failed',
            'status'            => ['required',Rule::in(['pending','paid','failed'])],
            'allocations'       => 'nullable|array',
            'allocations.*.invoice_id' => 'required|exists:mm_invoices,id',
            'allocations.*.amount'     => 'required|numeric|min:0.01',
        ]);

        if (isset($validated['transaction_date'])) {
            $validated['transaction_date'] = \Carbon\Carbon::parse($validated['transaction_date'])->format('Y-m-d');
        }

        $plantId = session('active_plant_id');
        $validated['plant_id'] = $plantId;
        $validated['created_by'] = auth()->id();

        try {
            $payment = DB::transaction(function () use ($validated, $plantId) {
                $totalAllocated = 0;
                if (!empty($validated['allocations'])) {
                    $totalAllocated = array_sum(array_column($validated['allocations'], 'amount'));
                }

                $freshCash = (float) $validated['amount'];
                $usePreviousAdvance = !empty($validated['use_excess_amount']);
                $advanceConsumed = $usePreviousAdvance ? max(0.00, $totalAllocated - $freshCash) : 0.00;

                // A. Split-funding Scenario: Both Fresh Cash AND Previous Advance are used
                if ($advanceConsumed > 0.00 && !empty($validated['patron_id'])) {
                    $patronId = $validated['patron_id'];

                    // 1. Calculate and verify available advance balance
                    $totalExcessAccumulated = Payment::where('plant_id', $plantId)
                        ->where('patron_id', $patronId)
                        ->where('status', 'paid')
                        ->sum('excess_amount');

                    $totalExcessConsumed = Payment::where('plant_id', $plantId)
                        ->where('patron_id', $patronId)
                        ->where('status', 'paid')
                        ->where('use_excess_amount', true)
                        ->sum('amount');

                    $availableAdvance = max(0.00, $totalExcessAccumulated - $totalExcessConsumed);

                    if (round($advanceConsumed, 2) > round($availableAdvance, 2)) {
                        throw new \Exception("Insufficient patron advance balance! Available advance: ₹" . number_format($availableAdvance, 2) . ", attempting to use ₹" . number_format($advanceConsumed, 2) . ".");
                    }

                    // 2. Distribute allocations between fresh cash and previous advance
                    $freshAllocations = [];
                    $advanceAllocations = [];
                    $remainingFresh = $freshCash;

                    foreach ($validated['allocations'] as $alloc) {
                        $allocAmount = (float) $alloc['amount'];
                        if ($remainingFresh > 0) {
                            $takeFresh = min($remainingFresh, $allocAmount);
                            $freshAllocations[] = [
                                'invoice_id' => $alloc['invoice_id'],
                                'amount' => $takeFresh
                            ];
                            $remainingFresh -= $takeFresh;
                            $allocAmount -= $takeFresh;
                        }
                        if ($allocAmount > 0) {
                            $advanceAllocations[] = [
                                'invoice_id' => $alloc['invoice_id'],
                                'amount' => $allocAmount
                            ];
                        }
                    }

                    // 3. Create the Fresh Cash Payment
                    $freshPaymentData = $validated;
                    $freshPaymentData['use_excess_amount'] = false;
                    $freshPaymentData['excess_amount'] = 0.00;
                    $freshPayment = Payment::create(Arr::except($freshPaymentData, ['allocations']));

                    foreach ($freshAllocations as $alloc) {
                        $invoice = Invoice::findOrFail($alloc['invoice_id']);
                        PaymentAllocation::create([
                            'payment_id' => $freshPayment->id,
                            'invoice_id' => $invoice->id,
                            'amount'     => $alloc['amount'],
                            'created_by' => auth()->id(),
                        ]);

                        $invoice->paid_amount += $alloc['amount'];
                        $invoice->balance_amount = max(0.00, $invoice->total_amount - $invoice->paid_amount);
                        if ($invoice->balance_amount <= 0) {
                            $invoice->status = Invoice::STATUS_PAID;
                        }
                        $invoice->save();
                    }

                    if ($freshPayment->status === 'paid') {
                        $this->syncTransactions($freshPayment);
                        $freshPayment->postToAccounting();
                    }

                    // 4. Create the Advance Consumed Payment
                    $advancePaymentData = $validated;
                    $advancePaymentData['amount'] = $advanceConsumed;
                    $advancePaymentData['use_excess_amount'] = true;
                    $advancePaymentData['excess_amount'] = 0.00;
                    $advancePaymentData['reference'] = null; // Let observer generate a fresh auto-incremented number
                    $advancePaymentData['description'] = trim(($advancePaymentData['description'] ?? '') . " (Advance Balance Applied)");
                    
                    $advancePayment = Payment::create(Arr::except($advancePaymentData, ['allocations']));

                    foreach ($advanceAllocations as $alloc) {
                        $invoice = Invoice::findOrFail($alloc['invoice_id']);
                        PaymentAllocation::create([
                            'payment_id' => $advancePayment->id,
                            'invoice_id' => $invoice->id,
                            'amount'     => $alloc['amount'],
                            'created_by' => auth()->id(),
                        ]);

                        $invoice->paid_amount += $alloc['amount'];
                        $invoice->balance_amount = max(0.00, $invoice->total_amount - $invoice->paid_amount);
                        if ($invoice->balance_amount <= 0) {
                            $invoice->status = Invoice::STATUS_PAID;
                        }
                        $invoice->save();
                    }

                    if ($advancePayment->status === 'paid') {
                        $this->syncTransactions($advancePayment);
                        $advancePayment->postToAccounting();
                    }

                    return $freshPayment;
                }

                // B. Standard Scenario (No split or pure advance without fresh cash mix)
                $payment = Payment::create(Arr::except($validated, ['allocations']));

                if (!empty($validated['allocations'])) {
                    foreach ($validated['allocations'] as $allocationData) {
                        $invoice = Invoice::findOrFail($allocationData['invoice_id']);
                        
                        PaymentAllocation::create([
                            'payment_id' => $payment->id,
                            'invoice_id' => $invoice->id,
                            'amount'     => $allocationData['amount'],
                            'created_by' => auth()->id(),
                        ]);

                        $invoice->paid_amount += $allocationData['amount'];
                        $invoice->balance_amount = max(0.00, $invoice->total_amount - $invoice->paid_amount);
                        
                        if ($invoice->balance_amount <= 0) {
                            $invoice->status = Invoice::STATUS_PAID;
                        }
                        $invoice->save();
                    }

                    // Strict protection: allocation sum cannot exceed payment amount (plus previous excess if allowed)
                    $fundingLimit = $payment->amount;
                    if ($payment->use_excess_amount && !empty($validated['patron_id'])) {
                        // verify the advance balance
                        $patronId = $validated['patron_id'];
                        $totalExcessAccumulated = Payment::where('plant_id', $plantId)
                            ->where('patron_id', $patronId)
                            ->where('status', 'paid')
                            ->sum('excess_amount');

                        $totalExcessConsumed = Payment::where('plant_id', $plantId)
                            ->where('patron_id', $patronId)
                            ->where('status', 'paid')
                            ->where('use_excess_amount', true)
                            ->sum('amount');

                        $availableAdvance = max(0.00, $totalExcessAccumulated - $totalExcessConsumed);
                        $fundingLimit += $availableAdvance;
                    }

                    if (round($totalAllocated, 2) > round($fundingLimit, 2)) {
                        throw new \Exception("Total allocated amount (₹" . number_format($totalAllocated, 2) . ") cannot exceed the total available funding (₹" . number_format($fundingLimit, 2) . ").");
                    }
                }

                if ($payment->status === 'paid') {
                    $this->syncTransactions($payment);
                    $payment->postToAccounting();
                }

                return $payment;
            });

            $message = $payment->status === 'paid' 
                ? 'Transaction recorded and posted to accounting successfully.' 
                : 'Transaction recorded successfully (Pending).';

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to post payment transaction: " . $e->getMessage(), [
                'exception' => $e,
                'request_payload' => $request->all()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to record transaction: ' . $e->getMessage()
            ]);
        }
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorizeModule('edit');

        if ($payment->status === 'paid') {
            return redirect()->back()->withErrors(['error' => 'paid transactions cannot be updated.']);
        }

        $validated = $request->validate([
            'transaction_date'  => 'required|date',
            'ledger_id'         => 'required|exists:mm_ledgers,id',
            'patron_id'         => 'nullable|exists:mm_patrons,id',
            'partner_type'      => 'nullable|string',
            'origin'            => 'nullable|string',
            'origin_id'         => 'nullable|integer',
            'amount'            => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request) {
                    $useExcess = filter_var($request->input('use_excess_amount'), FILTER_VALIDATE_BOOLEAN);
                    if (!$useExcess && $value < 0.01) {
                        $fail('The amount field must be at least 0.01 when not using previous advance.');
                    } elseif ($value < 0) {
                        $fail('The amount field cannot be negative.');
                    }
                }
            ],
            'excess_amount'     => 'nullable|numeric|min:0',
            'use_excess_amount' => 'nullable|boolean',
            'transaction_type'  => 'required|in:payment,receipt',
            'transaction_mode'  => 'nullable|string',
            'reconcile_opening_balance' => 'nullable|boolean',
            'batch_deposit'     => 'nullable|boolean',
            'description'       => 'nullable|string',
            'reference'         => 'nullable|string|max:100',
            'status'            => 'required|in:pending,paid,failed',
        ]);

        if (isset($validated['transaction_date'])) {
            $validated['transaction_date'] = \Carbon\Carbon::parse($validated['transaction_date'])->format('Y-m-d');
        }

        try {
            DB::transaction(function () use ($payment, $validated) {
                $payment->update($validated);
                
                if ($payment->status === 'paid') {
                    $this->syncTransactions($payment);
                    $payment->postToAccounting();
                }
            });
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update transaction: ' . $e->getMessage()]);
        }

        $message = $validated['status'] === 'paid' 
            ? 'Transaction updated and posted to accounting successfully.' 
            : 'Transaction updated successfully.';

        return redirect()->back()->with('success', $message);
    }

    /**
     * Internal helper to sync debit/credit lines in payment_transactions table.
     * Throws exception on failure to ensure DB transaction rollback.
     */
    private function syncTransactions(Payment $payment)
    {
        // 1. Initial Validation for Transaction Integrity
        if (!$payment->id || !$payment->amount || $payment->amount <= 0) {
            throw new \Exception('Invalid payment data for accounting sync.');
        }

        // Clear existing lines to rebuild (for update simplicity)
        PaymentTransaction::where('payment_id', $payment->id)->delete();

        $plantId = $payment->plant_id ?? session('active_plant_id');
        
        if (!$plantId) {
            throw new \Exception('Cannot sync transactions: Plant ID is missing.');
        }

        $commonData = [
            'plant_id'         => $plantId,
            'payment_id'       => $payment->id,
            'origin'           => $payment->origin ?? 'payment',
            'origin_id'        => $payment->origin_id ?? $payment->id,
            'transaction_date' => $payment->transaction_date,
            'reference'        => $payment->reference,
            'description'      => $payment->description,
            'status'           => 'paid',
            'created_by'       => auth()->id(),
        ];

        $totalDebit = 0;
        $totalCredit = 0;

        if ($payment->transaction_type === 'payment') {
            // DEBIT Side: The Partner/Patron
            if ($payment->patron_id) {
                $patronLedgerId = $payment->patron?->ledger_id;
                if (!$patronLedgerId) {
                    $patronLedgerId = Ledger::where('title', 'like', "%Sundry Creditor%")
                        ->where('plant_id', $plantId)
                        ->value('id');
                }

                PaymentTransaction::create(array_merge($commonData, [
                    'ledger_id'    => $patronLedgerId, 
                    'patron_id'    => $payment->patron_id,
                    'debit_amount' => $payment->amount,
                ]));
                $totalDebit += $payment->amount;
            }

            // CREDIT Side: The Cash/Bank Ledger
            PaymentTransaction::create(array_merge($commonData, [
                'ledger_id'     => $payment->ledger_id,
                'credit_amount' => $payment->amount,
            ]));
            $totalCredit += $payment->amount;
        } else {
            // receipt
            // DEBIT Side: The Cash/Bank Ledger
            PaymentTransaction::create(array_merge($commonData, [
                'ledger_id'    => $payment->ledger_id,
                'debit_amount' => $payment->amount,
            ]));
            $totalDebit += $payment->amount;

            // CREDIT Side: The Partner/Patron
            if ($payment->patron_id) {
                $patronLedgerId = $payment->patron?->ledger_id;
                if (!$patronLedgerId) {
                    $patronLedgerId = Ledger::where('title', 'like', "%Sundry Debtor%")
                        ->where('plant_id', $plantId)
                        ->value('id');
                }

                PaymentTransaction::create(array_merge($commonData, [
                    'ledger_id'     => $patronLedgerId,
                    'patron_id'     => $payment->patron_id,
                    'credit_amount' => $payment->amount,
                ]));
                $totalCredit += $payment->amount;
            }
        }

        // 2. Strict Balance Validation
        if (number_format($totalDebit, 2) !== number_format($totalCredit, 2)) {
            throw new \Exception("Accounting transaction is unbalanced! Debit: $totalDebit, Credit: $totalCredit. Check if Partner is selected.");
        }
    }

    public function destroy(Payment $payment)
    {
        $this->authorizeModule('delete');
        
        if ($payment->status === 'paid') {
            return redirect()->back()->withErrors(['error' => 'paid transactions cannot be deleted. Void or reverse instead.']);
        }

        try {
            DB::transaction(function () use ($payment) {
                // 1. Reverse Invoice Allocations
                foreach ($payment->allocations as $allocation) {
                    $invoice = $allocation->invoice;
                    if ($invoice) {
                        $invoice->paid_amount -= $allocation->amount;
                        $invoice->balance_amount = $invoice->total_amount - $invoice->paid_amount;
                        
                        // If it was paid, move it back to approved
                        if ($invoice->balance_amount > 0 && $invoice->status === Invoice::STATUS_PAID) {
                            $invoice->status = Invoice::STATUS_APPROVED;
                        }
                        $invoice->save();
                    }
                    $allocation->delete();
                }

                // 2. Log to Audit Tables
                PaymentAudit::create([
                    'payment_id' => $payment->id,
                    'data'       => $payment->toArray(),
                    'action'     => 'deleted',
                    'action_by'  => auth()->id(),
                ]);

                $transactions = PaymentTransaction::where('payment_id', $payment->id)->get();
                foreach ($transactions as $transaction) {
                    PaymentTransactionAudit::create([
                        'payment_transaction_id' => $transaction->id,
                        'payment_id'             => $payment->id,
                        'data'                   => $transaction->toArray(),
                        'action'                 => 'deleted',
                        'action_by'              => auth()->id(),
                    ]);
                }

                // 3. Actual Deletion
                PaymentTransaction::where('payment_id', $payment->id)->delete();
                $payment->delete();
            });
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Deletion failed: ' . $e->getMessage()]);
        }
        
        return redirect()->back()->with('success', 'Transaction deleted and archived.');
    }

    public function getNextReferenceNumber(Request $request)
    {
        $request->validate([
            'ledger_id'        => 'required|exists:mm_ledgers,id',
            'transaction_type' => 'required|in:payment,receipt',
            'transaction_date' => 'nullable|date',
        ]);

        $plantId = session('active_plant_id', 1);
        $ledgerId = $request->query('ledger_id');
        $transactionType = $request->query('transaction_type');
        $transactionDate = $request->query('transaction_date');

        $nextReference = Payment::generateReferenceNumber(
            $plantId,
            $ledgerId,
            $transactionType,
            $transactionDate
        );

        return response()->json([
            'reference' => $nextReference,
        ]);
    }

    public function getPatronAdvanceBalance(Request $request)
    {
        $request->validate([
            'patron_id' => 'required|exists:mm_patrons,id',
        ]);

        $patronId = $request->query('patron_id');
        $plantId = session('active_plant_id', 1);

        $totalExcessAccumulated = Payment::where('plant_id', $plantId)
            ->where('patron_id', $patronId)
            ->where('status', 'paid')
            ->sum('excess_amount');

        $totalExcessConsumed = Payment::where('plant_id', $plantId)
            ->where('patron_id', $patronId)
            ->where('status', 'paid')
            ->where('use_excess_amount', true)
            ->sum('amount');

        $availableBalance = max(0.00, $totalExcessAccumulated - $totalExcessConsumed);

        return response()->json([
            'available_excess_amount' => round($availableBalance, 2),
        ]);
    }
}

