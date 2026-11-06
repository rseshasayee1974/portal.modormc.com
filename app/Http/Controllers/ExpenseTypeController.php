<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Ledger;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ExpenseTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'expense_types';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = app(PlantContextService::class)->requirePlantId();

        return Inertia::render('Expenses/Types/Index', [
            'expenseTypes' => ExpenseType::with('ledger')
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'ledgers' => Ledger::where('plant_id', $plantId)->orderBy('title')->get(['id', 'title', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $plantId = app(PlantContextService::class)->requirePlantId();
        $validated = $this->validatedType($request, $plantId);
        DB::transaction(fn () => ExpenseType::create($validated + ['plant_id' => $plantId]), attempts: 3);

        return redirect()->back()->with('success', 'Expense type created.');
    }

    public function update(Request $request, ExpenseType $expenseType)
    {
        $this->authorizeModule('edit');

        $plantId = app(PlantContextService::class)->requirePlantId();
        abort_unless((int) $expenseType->plant_id === $plantId, 404);
        $validated = $this->validatedType($request, $plantId);
        DB::transaction(function () use ($expenseType, $validated) {
            ExpenseType::query()->lockForUpdate()->findOrFail($expenseType->id)->update($validated);
        }, attempts: 3);

        return redirect()->back()->with('success', 'Expense type updated.');
    }

    public function destroy(ExpenseType $expenseType)
    {
        $this->authorizeModule('delete');
        abort_unless((int) $expenseType->plant_id === app(PlantContextService::class)->requirePlantId(), 404);
        DB::transaction(function () use ($expenseType) {
            $record = ExpenseType::query()->lockForUpdate()->findOrFail($expenseType->id);
            if (Expense::query()->where('expense_type_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['expense_type' => 'This category is used by expenses. Set it to inactive instead.']);
            }
            $record->delete();
        }, attempts: 3);
        return redirect()->back()->with('success', 'Expense type deleted.');
    }

    private function validatedType(Request $request, int $plantId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:250'],
            'ledger_id' => ['nullable', 'integer', Rule::exists('mm_ledgers', 'id')->where('plant_id', $plantId)->withoutTrashed()],
            'status' => ['sometimes', 'boolean'],
        ]);
    }
}
