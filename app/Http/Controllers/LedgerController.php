<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\AccountsType;
use App\Http\Requests\StoreLedgerRequest;
use App\Http\Requests\UpdateLedgerRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerController extends Controller
{
    /**
     * Display a listing of ledgers.
     */
    public function index()
    {
        $plantId = session('active_plant_id');
        $ledgers = Ledger::with('accountType')
            ->where('plant_id', $plantId)
            ->orderBy('id', 'desc')
            ->get();

        $accountTypes = AccountsType::where('plant_id', $plantId)->get();

        return Inertia::render('Ledger/Index', [
            'ledgers'       => $ledgers,
            'account_types' => $accountTypes,
        ]);
    }

    /**
     * Store a newly created ledger.
     */
    public function store(StoreLedgerRequest $request)
    {
        $plantId = session('active_plant_id');
        $validated = $request->validated();

        $accountType = AccountsType::with('account')->findOrFail($validated['account_type_id']);
        $category = strtoupper($accountType->account->title ?? '');

        // Generate Code if not provided
        $code = $validated['code'] ?? Ledger::generateNextCodeForCategory($category, $plantId);

        // Range Validation logic migrated to model
        $tempLedger = new Ledger();
        if (!$tempLedger->isValidCodeForCategory($code, $category)) {
            return response()->json([
                'errors' => ['code' => ["The code '$code' is out of range for $category series."]]
            ], 422);
        }

        $ledger = Ledger::create(array_merge($validated, ['code' => $code]));

        return response()->json([
            'message' => 'Ledger Created Successfully!',
            'ledger'  => $ledger->load('accountType'),
        ], 201);
    }

    /**
     * Display the specified ledger.
     */
    public function show($id)
    {
        return response()->json(['ledger' => Ledger::with('accountType')->findOrFail($id)]);
    }

    /**
     * Update the specified ledger.
     */
    public function update(UpdateLedgerRequest $request, Ledger $ledger)
    {
        $validated = $request->validated();
        
        $accountType = AccountsType::with('account')->findOrFail($validated['account_type_id']);
        $category = strtoupper($accountType->account->title ?? '');

        if (!empty($validated['code']) && !$ledger->isValidCodeForCategory($validated['code'], $category)) {
            return response()->json([
                'errors' => ['code' => ["The code '{$validated['code']}' is out of range for $category series."]]
            ], 422);
        }

        $ledger->update($validated);

        return response()->json([
            'message' => 'Ledger Updated Successfully!',
            'ledger'  => $ledger->load('accountType'),
        ]);
    }

    /**
     * Soft-delete the specified ledger.
     */
    public function destroy(Ledger $ledger)
    {
        $ledger->delete();

        return response()->json([
            'message' => 'Ledger Deleted Successfully!',
        ]);
    }

    /**
     * API: Get the next available code for a specific category.
     */
    public function getNextCode(Request $request)
    {
        $category = $request->query('category');
        $plantId = session('active_plant_id');

        if (!$category) return response()->json(['code' => '']);

        return response()->json([
            'code' => Ledger::generateNextCodeForCategory($category, $plantId)
        ]);
    }
}
