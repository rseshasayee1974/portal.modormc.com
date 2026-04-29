<?php

namespace App\Http\Controllers;

use App\Models\AccountsType;
use App\Models\Accounts;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

use App\Http\Controllers\Concerns\GeneratesAccountingCode;

class AccountsTypeController extends Controller
{
    use GeneratesAccountingCode;

    /**
     * Display a listing of account types.
     */
    public function index()
    {
        $account_types = AccountsType::with(['account', 'parent'])
            ->where('plant_id', session('active_plant_id'))
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->get();
// dd(session('active_plant_id'));
        $accounts = Accounts::whereNull('deleted_at')->get();

        return Inertia::render('AccountsType/Index', [
            'account_types' => $account_types,
            'accounts'      => $accounts,
        ]);
    }
    

    /**
     * Store a newly created account type.
     */
    public function store(Request $request)
    {
        $entityId = session('active_entity_id');

        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'exists:mm_accounts,id'],
            'title'      => ['required', 'string', 'max:255'],
            'code'       => [
                'nullable', 
                'string', 
                'max:50',
                Rule::unique('mm_account_types')->where(fn ($q) => $q->where('plant_id', session('active_plant_id'))->whereNull('deleted_at'))
            ],
            'parent_id'  => ['nullable', 'integer', 'exists:mm_account_types,id'],
        ]);

        $account = Accounts::findOrFail($validated['account_id']);
        $category = strtoupper($account->title); 

        // Automated Code Generation if not provided
        $code = $validated['code'] ?? $this->generateNextCode($category, 'account_types', $entityId);

        // Range Validation
        if (!$this->validateCodeRange($code, $category)) {
            return response()->json([
                'errors' => ['code' => ["The code '$code' is out of range for $category series."]]
            ], 422);
        }

        $account_type = AccountsType::create([
            'plant_id'  => session('active_plant_id'),
            'account_id' => $validated['account_id'],
            'code'       => $code,
            'parent_id'  => $validated['parent_id'] ?? null,
            'title'      => $validated['title'],
            'status'     => 1,
            'created_at' => now(),
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message'      => 'Accounts Type Created Successfully!',
            'account_type' => $account_type->load(['account', 'parent']),
        ], 201);
    }

    /**
     * Edit form (not used with modal, but defined as per request).
     */
    

    /**
     * Update the specified account type.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'exists:mm_accounts,id'],
            'title'      => ['required', 'string', 'max:255'],
            'code'       => ['nullable', 'string', 'max:50'],
            'parent_id'  => ['nullable', 'integer', 'exists:mm_account_types,id'],
            'status'     => ['sometimes', 'integer', 'in:0,1'],
        ]);

        $account_type = AccountsType::findOrFail($id);

        $account_type->update(array_merge($validated, [
            'plant_id'   => session('active_plant_id'),
            'updated_at' => now(),
            'updated_by' => Auth::id(),
        ]));

        return response()->json([
            'message' => 'Accounts Type Updated Successfully!',
            'data'    => [$account_type->load(['account', 'parent'])],
        ], 200);
    }

    /**
     * Soft-delete the specified account type.
     */
    public function destroy($id)
    {
        $account_type = AccountsType::findOrFail($id);

        $account_type->deleted_by = Auth::id();
        $account_type->deleted_at  = now();
        $account_type->save();
        $account_type->delete();

        return response()->json([
            'message'      => 'Account Type Deleted Successfully!',
            'account_type' => $account_type,
        ]);
    }
}
