<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\PettyCashItem;
use App\Models\Plant;
use App\Services\PlantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $plantId = app(PlantContextService::class)->requirePlantId();

        return Inertia::render('Expenses/Index', [
            'expenses' => Expense::with(['expenseType', 'madeBy', 'ledger', 'machine', 'vendor', 'customer'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'expenseTypes' => toSelectOptions(ExpenseTypesDropdown(), 'name'),
            'ledgers'  => toSelectOptions(LedgersDropdown(), 'title'),
            'machines' => toSelectOptions(MachinesDropdown(plantId: $plantId), 'registration'),
            'patrons'  => toSelectOptions(PatronsDropdown(plantId: $plantId), 'legal_name'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = app(PlantContextService::class)->requirePlantId();
        $validated = $this->validatedExpense($request, $plantId);

        DB::transaction(function () use ($validated, $plantId) {
            Plant::query()->select('id')->lockForUpdate()->findOrFail($plantId);
            $this->lockExpenseType($validated['expense_type_id'], $plantId);
            Expense::create($validated + ['plant_id' => $plantId, 'made_by' => auth()->id()]);
        }, attempts: 3);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeModule('edit');
        $plantId = app(PlantContextService::class)->requirePlantId();
        abort_unless((int) $expense->plant_id === $plantId, 404);
        $validated = $this->validatedExpense($request, $plantId);

        DB::transaction(function () use ($expense, $validated, $plantId) {
            $this->lockExpenseType($validated['expense_type_id'], $plantId);
            $record = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->ensureUnlinked($record);
            $record->update($validated);
        }, attempts: 3);

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorizeModule('delete');
        abort_unless((int) $expense->plant_id === app(PlantContextService::class)->requirePlantId(), 404);

        DB::transaction(function () use ($expense) {
            $record = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->ensureUnlinked($record);
            $record->delete();
        }, attempts: 3);

        return back()->with('success', 'Expense deleted successfully.');
    }

    private function validatedExpense(Request $request, int $plantId): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')
            ->where('plant_id', $plantId)->withoutTrashed();

        return $request->validate([
            'expense_type_id' => ['required', 'integer', $exists('mm_expense_types')],
            'paid_through' => ['required', 'integer', $exists('mm_ledgers')],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'lt:1000000000000000'],
            'date' => ['required', 'date'],
            'vendor_id' => ['nullable', 'integer', $exists('mm_patrons')],
            'customer_id' => ['nullable', 'integer', $exists('mm_patrons')],
            'machine_id' => ['nullable', 'integer', $exists('mm_machines')],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function ensureUnlinked(Expense $expense): void
    {
        if (PettyCashItem::where('expense_id', $expense->id)->exists()) {
            throw ValidationException::withMessages(['expense' => 'This expense is linked to petty cash and cannot be changed.']);
        }
    }

    private function lockExpenseType(int $id, int $plantId): void
    {
        if (!ExpenseType::where('plant_id', $plantId)->lockForUpdate()->find($id, ['id'])) {
            throw ValidationException::withMessages(['expense_type_id' => 'The selected expense category is unavailable.']);
        }
    }
}
