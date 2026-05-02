<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

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
            'ledgers' => Ledger::where('plant_id', $plantId)->select('id', 'title')->get(),
            'patrons' => PatronsDropdown($plantId),
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
            'amount'            => 'required|numeric|min:0.01',
            'excess_amount'     => 'nullable|numeric|min:0',
            'use_excess_amount' => 'nullable|boolean',
            'transaction_type'  => 'required|in:mm_payment,receipt',
            'transaction_mode'  => 'nullable|string',
            'reconcile_opening_balance' => 'nullable|boolean',
            'batch_deposit'     => 'nullable|boolean',
            'description'       => 'nullable|string',
            'reference'         => 'nullable|string|max:100',
            'status'            => 'required|in:pending,completed,failed',
        ]);

        if (isset($validated['transaction_date'])) {
            $validated['transaction_date'] = \Carbon\Carbon::parse($validated['transaction_date'])->format('Y-m-d H:i:s');
        }

        $validated['plant_id'] = session('active_plant_id', 1);

        Payment::create($validated);

        return redirect()->back()->with('success', 'Transaction recorded successfully.');
    }

    public function update(Request $request, Payment $Payment)
    {
        $this->authorizeModule('edit');

        // Optional protection against updating completed payments
        if ($Payment->status === 'completed' && $request->status !== 'completed') {
            return redirect()->back()->withErrors(['status' => 'Cannot reverse a completed transaction.']);
        }

        $validated = $request->validate([
            'transaction_date'  => 'required|date',
            'ledger_id'         => 'required|exists:mm_ledgers,id',
            'patron_id'         => 'nullable|exists:mm_patrons,id',
            'partner_type'      => 'nullable|string',
            'amount'            => 'required|numeric|min:0.01',
            'excess_amount'     => 'nullable|numeric|min:0',
            'use_excess_amount' => 'nullable|boolean',
            'transaction_type'  => 'required|in:payment,receipt',
            'transaction_mode'  => 'nullable|string',
            'reconcile_opening_balance' => 'nullable|boolean',
            'batch_deposit'     => 'nullable|boolean',
            'description'       => 'nullable|string',
            'reference'         => 'nullable|string|max:100',
            'status'            => 'required|in:pending,completed,failed',
        ]);

        if (isset($validated['transaction_date'])) {
            $validated['transaction_date'] = \Carbon\Carbon::parse($validated['transaction_date'])->format('Y-m-d H:i:s');
        }

        $Payment->update($validated);

        return redirect()->back()->with('success', 'Transaction updated successfully.');
    }

    public function destroy(Payment $Payment)
    {
        $this->authorizeModule('delete');
        
        if ($Payment->status === 'completed') {
            return redirect()->back()->withErrors(['error' => 'Completed transactions cannot be deleted. Void or reverse instead.']);
        }

        $Payment->delete();
        
        return redirect()->back()->with('success', 'Transaction deleted.');
    }
}
