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
            'amount'            => 'required|numeric|min:0.01',
            'excess_amount'     => 'nullable|numeric|min:0',
            'use_excess_amount' => 'nullable|boolean',
            'transaction_type'  => 'required|in:payment,receipt',
            'transaction_mode'  => 'nullable|string',
            'reconcile_opening_balance' => 'nullable|boolean',
            'batch_deposit'     => 'nullable|boolean',
            'description'       => 'nullable|string',
            'reference'         => 'nullable|string|max:100',
            'status'            => 'required|in:pending,paid,failed',
            'allocations'       => 'nullable|array',
            'allocations.*.invoice_id' => 'required|exists:mm_invoices,id',
            'allocations.*.amount'     => 'required|numeric|min:0.01',
        ]);

        if (isset($validated['transaction_date'])) {
            $validated['transaction_date'] = \Carbon\Carbon::parse($validated['transaction_date'])->format('Y-m-d');
        }

        $validated['plant_id'] = session('active_plant_id', 1);
        $validated['created_by'] = auth()->id();

        try {
            $payment = DB::transaction(function () use ($validated) {
                // 1. Create base payment record
                $payment = Payment::create(Arr::except($validated, ['allocations']));

                // 2. Handle Allocations
                if (!empty($validated['allocations'])) {
                    $totalAllocated = 0;
                    foreach ($validated['allocations'] as $allocationData) {
                        $invoice = Invoice::findOrFail($allocationData['invoice_id']);
                        
                        PaymentAllocation::create([
                            'payment_id' => $payment->id,
                            'invoice_id' => $invoice->id,
                            'amount'     => $allocationData['amount'],
                            'created_by' => auth()->id(),
                        ]);

                        // Update Invoice paid amounts
                        $invoice->paid_amount += $allocationData['amount'];
                        $invoice->balance_amount = max(0.00, $invoice->total_amount - $invoice->paid_amount);
                        
                        if ($invoice->balance_amount <= 0) {
                            $invoice->status = Invoice::STATUS_PAID;
                        }
                        $invoice->save();

                        $totalAllocated += $allocationData['amount'];
                    }

                    // Strict protection: allocation sum cannot exceed payment amount
                    if (round($totalAllocated, 2) > round($payment->amount, 2)) {
                        throw new \Exception("Total allocated amount (₹" . number_format($totalAllocated, 2) . ") cannot exceed the payment amount (₹" . number_format($payment->amount, 2) . ").");
                    }
                }

                // 3. Post to accounting if state is paid
                if ($payment->status === 'paid') {
                    // Sync debit/credit lines
                    $this->syncTransactions($payment);

                    // Post journal entry
                    $payment->postToAccounting();
                }

                return $payment;
            });

            $message = $payment->status === 'paid' 
                ? 'Transaction recorded and posted to accounting successfully.' 
                : 'Transaction recorded successfully (Pending).';

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            // Log full stack trace for developer diagnosis
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
            'amount'            => 'required|numeric|min:0.01',
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
                    $patronLedgerId = \App\Models\Ledger::where('title', 'like', "%Sundry Creditor%")
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
                    $patronLedgerId = \App\Models\Ledger::where('title', 'like', "%Sundry Debtor%")
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

