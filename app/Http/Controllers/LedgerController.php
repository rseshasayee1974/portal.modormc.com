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
        return response()->json(['ledger' => Ledger::with('accountType')->where('plant_id', app(\App\Services\PlantContextService::class)->requirePlantId())->findOrFail($id)]);
    }

    /**
     * Update the specified ledger.
     */
    public function update(UpdateLedgerRequest $request, Ledger $ledger)
    {
        $this->authorizeModule('edit');
        abort_unless((int) $ledger->plant_id === app(\App\Services\PlantContextService::class)->requirePlantId(), 404);
        abort_if(\Illuminate\Support\Facades\Schema::hasTable('mm_accounting_accounts') && \Illuminate\Support\Facades\DB::table('mm_accounting_accounts')->where('ledger_id', $ledger->id)->exists(), 409, 'Maintain classified accounts through Accounting & GST');
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
    public function destroy(Request $request, Ledger $ledger)
    {
        $this->authorizeModule('delete');
        abort_unless((int) $ledger->plant_id === app(\App\Services\PlantContextService::class)->requirePlantId(), 404);
        abort_if(\Illuminate\Support\Facades\DB::table('mm_journal_entry_lines')->where('account_id', $ledger->id)->exists(), 409, 'A ledger with journal history cannot be deleted');
        abort_if(\Illuminate\Support\Facades\Schema::hasTable('mm_accounting_accounts') && \Illuminate\Support\Facades\DB::table('mm_accounting_accounts')->where('ledger_id', $ledger->id)->exists(), 409, 'Deactivate classified accounts through Accounting & GST');
        $ledger->delete();

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Ledger Deleted Successfully!',
            ]);
        }

        return redirect()->back()->with('success', 'Ledger Deleted Successfully!');
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

        return response()->json(app(\App\Services\DropdownCache::class)->remember(
            'LedgerDropdownEndpoint', [$type, $plantId], fn () => $query->get()
        ));
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
