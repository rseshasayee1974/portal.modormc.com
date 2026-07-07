<?php

namespace App\Http\Controllers;

use App\Models\BankAccountType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class BankAccountTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'bank_account_types';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('BankAccountTypes/Index', [
            'bankAccountTypes' => BankAccountType::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_bank_account_types,type',
        ]);

        $bankAccountType = BankAccountType::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'bankAccountType' => $bankAccountType,
                'message' => 'Bank Account Type created successfully.'
            ]);
        }

        return redirect()->route('bankaccounttypes.index')->with('success', 'Bank Account Type created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccountType $bankaccounttype)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_bank_account_types,type,' . $bankaccounttype->id,
        ]);

        $bankaccounttype->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'bankAccountType' => $bankaccounttype,
                'message' => 'Bank Account Type updated successfully.'
            ]);
        }
        return redirect()->route('bankaccounttypes.index')->with('success', 'Bank Account Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccountType $bankaccounttype)
    {
        $this->authorizeModule('delete');
        $bankaccounttype->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Bank Account Type deleted successfully.'
            ]);
        }

        return redirect()->route('bankaccounttypes.index')->with('success', 'Bank Account Type deleted successfully.');
    }
}
