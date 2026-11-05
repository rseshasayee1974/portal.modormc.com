<?php

namespace App\Http\Controllers;

use App\Accounting\ExpenseAccountingService;
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
            'expenses' => Expense::with(['expenseType', 'madeBy', 'ledger', 'machine', 'vendor', 'customer', 'accountingEntry'])
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
        if ($request->has('expenses')) {
            $rules = ['expenses' => ['required', 'array', 'min:1', 'max:100'],
                'expenses.*' => ['required', 'array:ref_no,expense_type_id,paid_through,amount,date,vendor_id,customer_id,machine_id,note']];
            foreach ($this->expenseRules($plantId) as $field => $fieldRules) {
                $rules['expenses.*.' . $field] = $fieldRules;
            }
            $rows = $request->validate($rules)['expenses'];
        } else {
            $rows = [$this->validatedExpense($request, $plantId)];
        }

        DB::transaction(function () use ($rows, $plantId, $request) {
            Plant::query()->select('id')->lockForUpdate()->findOrFail($plantId);
            foreach ($rows as $index => $row) {
                $this->lockExpenseType($row['expense_type_id'], $plantId);
                $this->validateReference($row['ref_no'] ?? null, $plantId, $request->has('expenses') ? "expenses.$index.ref_no" : 'ref_no');
                $expense = Expense::create($row + ['plant_id' => $plantId, 'made_by' => auth()->id()]);
                app(ExpenseAccountingService::class)->post($expense, $request->has('expenses') ? "expenses.$index." : '');
            }
        }, attempts: 3);

        return back()->with('success', count($rows) === 1 ? 'Expense recorded successfully.' : count($rows) . ' expenses recorded successfully.');
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeModule('edit');
        $plantId = app(PlantContextService::class)->requirePlantId();
        abort_unless((int) $expense->plant_id === $plantId, 404);
        $validated = $this->validatedExpense($request, $plantId);

        DB::transaction(function () use ($expense, $validated, $plantId) {
            Plant::query()->select('id')->lockForUpdate()->findOrFail($plantId);
            $this->lockExpenseType($validated['expense_type_id'], $plantId);
            $record = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->ensureUnlinked($record);
            if (empty($validated['ref_no']) && ($validated['ref_no'] ?? null) !== '0') {
                unset($validated['ref_no']);
            }
            $this->validateReference($validated['ref_no'] ?? null, $plantId, 'ref_no', $record->id);
            $record->update($validated);
            app(ExpenseAccountingService::class)->post($record);
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
            app(ExpenseAccountingService::class)->remove($record);
            $record->delete();
        }, attempts: 3);

        return back()->with('success', 'Expense deleted successfully.');
    }

    private function validatedExpense(Request $request, int $plantId): array
    {
        return $request->validate($this->expenseRules($plantId));
    }

    private function expenseRules(int $plantId): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')
            ->where('plant_id', $plantId)->withoutTrashed();

        return [
            'ref_no' => ['nullable', 'string', 'max:50'],
            'expense_type_id' => ['required', 'integer', $exists('mm_expense_types')],
            'paid_through' => ['required', 'integer', $exists('mm_ledgers')],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'lt:1000000000000000'],
            'date' => ['required', 'date'],
            'vendor_id' => ['nullable', 'integer', $exists('mm_patrons')],
            'customer_id' => ['nullable', 'integer', $exists('mm_patrons')],
            'machine_id' => ['nullable', 'integer', $exists('mm_machines')],
            'note' => ['nullable', 'string'],
        ];
    }

    private function validateReference(?string $reference, int $plantId, string $field, ?int $ignoreId = null): void
    {
        if ($reference === null || $reference === '') return;
        $query = Expense::withTrashed()->where('plant_id', $plantId)->where('ref_no', $reference);
        if ($ignoreId !== null) $query->whereKeyNot($ignoreId);
        if ($query->exists()) {
            throw ValidationException::withMessages([$field => 'This expense reference number is already in use.']);
        }
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
