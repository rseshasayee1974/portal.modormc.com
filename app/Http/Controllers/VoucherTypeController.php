<?php

namespace App\Http\Controllers;

use App\Models\VoucherType;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Concerns\AuthorizesModule;

class VoucherTypeController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'voucher_types';
    /**
     * Display a listing of global and entity-specific voucher types.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Admin') || $user->roles->contains('id', 1);

        $voucherTypes = VoucherType::orderBy('voucher_group', 'asc')
            ->orderBy('journal_name', 'asc')
            ->get();

        return Inertia::render('VoucherType/Index', [
            'voucherTypes' => $voucherTypes,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    /**
     * Store a newly created voucher type.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Admin') || $user->roles->contains('id', 1);

        $validated = $request->validate([
            'journal_name'        => ['required', 'string', 'max:100', Rule::unique('mm_voucher_types', 'journal_name')],
            'short_code'          => ['required', 'string', 'max:20', Rule::unique('mm_voucher_types', 'short_code')],
            'is_system_generated' => ['required', 'boolean'],
            'prefix'              => ['nullable', 'string', 'max:20'],
            'voucher_group'       => ['required', 'string', 'max:100'],
        ]);

        $createSystem = $validated['is_system_generated'] && $isSuperAdmin;
        
        // If not superadmin, force is_system_generated to false
        if ($validated['is_system_generated'] && !$isSuperAdmin) {
            return response()->json([
                'message' => 'Only Superadmins can create system-generated voucher types.',
            ], 403);
        }

        $voucherType = VoucherType::create(array_merge($validated, [
            'is_system_generated' => $createSystem ? 1 : 0
        ]));

        return response()->json([
            'message'     => 'Voucher Type Created Successfully!',
            'voucherType' => $voucherType,
        ], 201);
    }

    /**
     * Update the specified voucher type.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeModule('edit');
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Admin') || $user->roles->contains('id', 1);

        $voucherType = VoucherType::findOrFail($id);

        // Protect system templates from non-admins
        if ($voucherType->is_system_generated && !$isSuperAdmin) {
            return response()->json([
                'message' => 'Global system templates can only be modified by Superadmins.',
            ], 403);
        }

        $validated = $request->validate([
            'journal_name' => ['required', 'string', 'max:100', Rule::unique('mm_voucher_types', 'journal_name')->ignore($id)],
            'short_code'   => ['required', 'string', 'max:20', Rule::unique('mm_voucher_types', 'short_code')->ignore($id)],
            'is_system_generated' => ['required', 'boolean'],
            'prefix'              => ['nullable', 'string', 'max:20'],
            'voucher_group'       => ['required', 'string', 'max:100'],
        ]);

        // Non-admins cannot convert dynamic to system-generated
        if ($validated['is_system_generated'] && !$isSuperAdmin && !$voucherType->is_system_generated) {
             return response()->json([
                'message' => 'Only Superadmins can set voucher types as system-generated.',
            ], 403);
        }

        $voucherType->update($validated);

        return response()->json([
            'message'     => 'Voucher Type Updated Successfully!',
            'voucherType' => $voucherType,
        ]);
    }

    /**
     * Remove the specified voucher type.
     */
    public function destroy($id)
    {
        $this->authorizeModule('delete');
        $voucherType = VoucherType::findOrFail($id);
        
        if ($voucherType->is_system_generated) {
            return response()->json([
                'message' => 'System generated voucher types cannot be deleted.',
            ], 403);
        }

        $voucherType->delete();

        return response()->json([
            'message' => 'Voucher Type Deleted Successfully!',
        ]);
    }
}
