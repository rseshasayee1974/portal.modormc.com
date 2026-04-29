<?php

namespace App\Http\Controllers;

use App\Models\Accounts;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Concerns\GeneratesAccountingCode;

class AccountsController extends Controller
{
    use GeneratesAccountingCode;

    protected function activeEntityId(): ?int
    {
        return session('active_entity_id', session('entity_id'));
    }

    /**
     * Display a listing of all accounts for the active entity.
     */
    public function index()
    {
        $entityId = $this->activeEntityId();

        $accounts = Accounts::query()
            ->when($entityId, fn ($query) => $query->where('entity_id', $entityId))
            ->orderBy('title', 'asc')
            ->get();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show the form for creating — not used (modal-based).
     */
    public function create()
    {
        return redirect()->route('accounts.index');
    }

    /**
     * Store a newly created account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code'  => ['nullable', 'string', 'max:50'],
        ]);

        $category = strtoupper($validated['title']); // Main accounts are categories themselves
        
        // Automated Code Generation if not provided
        $code = $validated['code'] ?? $this->generateNextCode($category, 'accounts');

        // Validation against ranges
        if (!$this->validateCodeRange($code, $category)) {
            return response()->json([
                'errors' => ['code' => ["The code '$code' is out of range for $category category."]]
            ], 422);
        }

        $account = Accounts::create([
            'entity_id'  => $this->activeEntityId(),
            'code'       => $code,
            'title'      => $validated['title'],
            'status'     => 1,
            'created_by' => Auth::id(),
            'created'    => now(),
        ]);

        return response()->json([
            'message' => 'Account created successfully!',
            'account' => $account,
        ], 201);
    }

    /**
     * Show a single account (API use only).
     */
    public function show(string $id)
    {
                $account = Accounts::findOrFail($id);

        return response()->json(['account' => $account]);
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'title'  => ['required', 'string', 'max:255'],
            'code'   => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ]);

                $account = Accounts::findOrFail($id);


        $account->update(array_merge($validated, [
            'modified'    => now(),
            'updated_by' => Auth::id(),
        ]));

        return response()->json([
            'message' => 'Account updated successfully!',
            'account' => $account->fresh(),
        ]);
    }

    /**
     * Soft-delete the specified account.
     */
    public function destroy(string $id)
    {
        $account = Accounts::findOrFail($id);

        $account->updated_by = Auth::id();
        $account->save();
        $account->delete();

        return response()->json([
            'message' => 'Account deleted successfully!',
        ]);
    }
}
