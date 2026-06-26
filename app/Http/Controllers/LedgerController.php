<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\AccountsType;
use App\Http\Requests\StoreLedgerRequest;
use App\Http\Requests\UpdateLedgerRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Http\Controllers\Concerns\AuthorizesModule;

class LedgerController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'ledgers';
    /**
     * Display a listing of ledgers.
     */
    public function index()
    {
        $this->authorizeModule('menu');
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
        $this->authorizeModule('create');
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
        $this->authorizeModule('show');
        return response()->json(['ledger' => Ledger::with('accountType')->findOrFail($id)]);
    }

    /**
     * Update the specified ledger.
     */
    public function update(UpdateLedgerRequest $request, Ledger $ledger)
    {
        $this->authorizeModule('edit');
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
        $this->authorizeModule('delete');
        $ledger->delete();

        return response()->json([
            'message' => 'Ledger Deleted Successfully!',
        ]);
    }

    public function dropdown(Request $request)
    {
        $plantId = session('active_plant_id');
        $type = $request->query('type');
        
        $query = Ledger::where('plant_id', $plantId)
            ->select('id as value', 'code', 'title as label');

        if (in_array(strtolower($type), ['payment', 'receipt'])) {
            // For payments and receipts, we only show Cash and Bank ledgers
            $query->whereHas('accountType', function($q) {
                $q->whereIn('title', ['Current Assets']);
            });
        } elseif ($type) {
            // Generic filter by account category title
            $query->whereHas('accountType', function($q) use ($type) {
                $q->where('account_type', strtoupper($type));
            });
        }

        return response()->json($query->get());
    }

    /**
     * API: Get the next available code for a specific category.
     */
    public function getNextCode(Request $request)
    {
        $this->authorizeModule('show');
        $category = $request->query('category');
        $plantId = session('active_plant_id');

        if (!$category) return response()->json(['code' => '']);

        return response()->json([
            'code' => Ledger::generateNextCodeForCategory($category, $plantId)
        ]);
    }
}
