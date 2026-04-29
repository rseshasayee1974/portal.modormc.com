<?php

namespace App\Http\Controllers;

use App\Models\PettyCash;
use App\Models\PettyCashItem;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Patron;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PettyCashController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'petty_cash';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('PettyCash/Index', [
            'pettyCashes'  => PettyCash::with(['items.expense.expenseType', 'paidByUser', 'paidToUser', 'createdBy'])
                ->where('plant_id', $plantId)
                ->latest()
                ->get(),
            'expenses'     => Expense::with('expenseType')
                ->where('plant_id', $plantId)
                ->select('id', 'ref_no', 'expense_type_id', 'amount')
                ->get(),
            'expenseTypes' => ExpenseType::where('plant_id', $plantId)->select('id', 'name')->get(),
            'patrons'      => Patron::select('id', 'name')->get(),
            'users'        => User::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'date'            => 'required|date',
            'opening_balance' => 'required|numeric|min:0',
            'paid_by'         => 'nullable|exists:mm_users,id',
            'paid_to'         => 'nullable|exists:mm_users,id',
            'request_amount'  => 'nullable|numeric',
            'items'           => 'required|array|min:1',
            'items.*.expense_id'  => 'required|exists:mm_expenses,id',
            'items.*.amount'      => 'required|numeric|min:0',
            'items.*.debit'       => 'required|numeric|min:0',
            'items.*.credit'      => 'required|numeric|min:0',
            'items.*.date'        => 'required|date',
            'items.*.description' => 'nullable|string',
            'items.*.remarks'     => 'nullable|string|max:250',
        ]);

        DB::transaction(function () use ($validated) {
            $plantId = session('active_plant_id', 1);

            $pettyCash = PettyCash::create([
                'plant_id'        => $plantId,
                'date'            => $validated['date'],
                'opening_balance' => $validated['opening_balance'],
                'paid_by'         => $validated['paid_by'] ?? null,
                'paid_to'         => $validated['paid_to'] ?? null,
                'request_amount'  => $validated['request_amount'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $pettyCash->items()->create(array_merge($item, ['plant_id' => $plantId]));
            }

            // Closing balance auto-calculated via model observer
        });

        return redirect()->back()->with('success', 'Petty cash register created.');
    }

    public function update(Request $request, PettyCash $pettyCash)
    {
        $this->authorizeModule('edit');

        if ($pettyCash->closed_status) {
            return redirect()->back()->withErrors(['error' => 'Cannot edit a closed petty cash register.']);
        }

        $validated = $request->validate([
            'date'            => 'required|date',
            'opening_balance' => 'required|numeric|min:0',
            'paid_by'         => 'nullable|exists:mm_users,id',
            'paid_to'         => 'nullable|exists:mm_users,id',
            'request_amount'  => 'nullable|numeric',
            'items'           => 'required|array|min:1',
            'items.*.id'          => 'nullable|integer',
            'items.*.expense_id'  => 'required|exists:mm_expenses,id',
            'items.*.amount'      => 'required|numeric|min:0',
            'items.*.debit'       => 'required|numeric|min:0',
            'items.*.credit'      => 'required|numeric|min:0',
            'items.*.date'        => 'required|date',
            'items.*.description' => 'nullable|string',
            'items.*.remarks'     => 'nullable|string|max:250',
        ]);

        DB::transaction(function () use ($validated, $pettyCash) {
            $pettyCash->update([
                'date'            => $validated['date'],
                'opening_balance' => $validated['opening_balance'],
                'paid_by'         => $validated['paid_by'] ?? null,
                'paid_to'         => $validated['paid_to'] ?? null,
                'request_amount'  => $validated['request_amount'] ?? null,
            ]);

            $keptIds = collect($validated['items'])->pluck('id')->filter()->toArray();
            $pettyCash->items()->whereNotIn('id', $keptIds)->delete();

            foreach ($validated['items'] as $item) {
                if (isset($item['id'])) {
                    PettyCashItem::where('id', $item['id'])
                        ->update(collect($item)->except('id')->toArray());
                } else {
                    $pettyCash->items()->create(array_merge($item, [
                        'plant_id' => $pettyCash->plant_id
                    ]));
                }
            }

            $pettyCash->recalculate();
        });

        return redirect()->back()->with('success', 'Petty cash register updated.');
    }

    public function destroy(PettyCash $pettyCash)
    {
        $this->authorizeModule('delete');

        if ($pettyCash->journal_status) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete a journalized petty cash.']);
        }

        $pettyCash->delete();
        return redirect()->back()->with('success', 'Petty cash voided.');
    }

    /**
     * Close the petty cash register — no further edits after this.
     */
    public function close(PettyCash $pettyCash)
    {
        $this->authorizeModule('edit');
        $pettyCash->recalculate(); // Ensure balance is correct before locking
        $pettyCash->update(['closed_status' => true]);
        return redirect()->back()->with('success', 'Petty cash register closed.');
    }
}
