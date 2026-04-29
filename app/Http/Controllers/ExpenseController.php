<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ExpenseController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'expenses';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('Expenses/Index', [
            'expenses' => Expense::with(['expenseType', 'madeBy', 'ledger', 'machine', 'vendor'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'expenseTypes' => ExpenseType::where('plant_id', $plantId)
                ->where('status', true)
                ->select('id', 'name')
                ->get(),
            'ledgers'  => Ledger::select('id', 'name', 'code')->get(),
            'machines' => MachinesDropdown($plantId),
            'patrons'  => PatronsDropdown($plantId),
        ]);
    }

    /**
     * Expense module: CREATE only — no update allowed per business rule.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'expense_type_id' => 'required|exists:mm_expense_types,id',
            'paid_through'    => 'required|exists:mm_ledgers,id',
            'amount'          => 'required|numeric|min:0.01',
            'date'            => 'required|date',
            'vendor_id'       => 'nullable|exists:mm_patrons,id',
            'customer_id'     => 'nullable|exists:mm_patrons,id',
            'machine_id'      => 'nullable|exists:mm_machines,id',
            'note'            => 'nullable|string',
        ]);

        $validated['plant_id'] = session('active_plant_id', 1);
        $validated['made_by']  = auth()->id();

        Expense::create($validated);

        return redirect()->back()->with('success', 'Expense recorded successfully.');
    }

    /**
     * Note: No update() method — per business requirement, expenses are immutable.
     */

    public function destroy(Expense $expense)
    {
        $this->authorizeModule('delete');
        // Note: Per requirements, DELETE is not allowed after creation.
        // Returning forbidden response.
        abort(403, 'Expenses cannot be deleted once recorded.');
    }
}
