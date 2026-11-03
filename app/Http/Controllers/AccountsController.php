<?php

namespace App\Http\Controllers;

use App\Models\Accounts;
use App\Models\Entity;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\GeneratesAccountingCode;

class AccountsController extends Controller
{
    use GeneratesAccountingCode;
    use AuthorizesModule;

    protected string $module = 'accounts';

    protected function activeEntityId(): ?int
    {
        return session('active_entity_id', session('entity_id'));
    }

    /**
     * Display a listing of all accounts for the active entity.
     */
    public function index()
    {
        $this->authorizeModule('menu');
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
        $this->authorizeModule('create');
        return redirect()->route('accounts.index');
    }

    /**
     * Store a newly created account.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code'  => ['nullable', 'string', 'max:50'],
        ]);

        $category = strtoupper(trim($validated['title']));
        if (!isset($this->ranges[$category])) {
            throw ValidationException::withMessages(['title' => 'Select a valid account category.']);
        }
        $entityId = $this->activeEntityId();
        abort_unless($entityId, 403, 'Select an active entity.');

        $account = DB::transaction(function () use ($entityId, $category, $validated) {
            Entity::query()->select('id')->lockForUpdate()->findOrFail($entityId);
            $code = $validated['code'] ?? $this->generateNextCode($category, 'accounts');

            if (!$this->validateCodeRange($code, $category)) {
                throw ValidationException::withMessages(['code' => "The code '$code' is out of range for $category category."]);
            }
            if (Accounts::where('entity_id', $entityId)->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => "The code '$code' is already in use."]);
            }

            return Accounts::create([
                'entity_id'  => $entityId,
                'code'       => $code,
                'title'      => $validated['title'],
                'status'     => 1,
                'created_by' => Auth::id(),
                'created'    => now(),
            ]);
        }, attempts: 3);

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
        $this->authorizeModule('show');
                $account = Accounts::findOrFail($id);

        return response()->json(['account' => $account]);
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, string $id)
    {
        $this->authorizeModule('edit');
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
        $this->authorizeModule('delete');
        $account = Accounts::findOrFail($id);

        $account->updated_by = Auth::id();
        $account->save();
        $account->delete();

        return response()->json([
            'message' => 'Account deleted successfully!',
        ]);
    }
}
