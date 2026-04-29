<?php

namespace App\Http\Controllers;

use App\Models\ExpenseType;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ExpenseTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'expense_types';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('Expenses/Types/Index', [
            'expenseTypes' => ExpenseType::with('ledger')
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'ledgers' => Ledger::select('id', 'name', 'code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name'      => 'required|string|max:250',
            'ledger_id' => 'nullable|exists:mm_ledgers,id',
        ]);

        $validated['plant_id'] = session('active_plant_id', 1);

        ExpenseType::create($validated);

        return redirect()->back()->with('success', 'Expense type created.');
    }

    public function update(Request $request, ExpenseType $expenseType)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name'      => 'required|string|max:250',
            'ledger_id' => 'nullable|exists:mm_ledgers,id',
            'status'    => 'boolean',
        ]);

        $expenseType->update($validated);

        return redirect()->back()->with('success', 'Expense type updated.');
    }

    public function destroy(ExpenseType $expenseType)
    {
        $this->authorizeModule('delete');
        $expenseType->delete();
        return redirect()->back()->with('success', 'Expense type deleted.');
    }
}
