<?php

namespace App\Http\Controllers;

use App\Models\VoucherType;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class VoucherTypeController extends Controller
{
    /**
     * Display a listing of global and entity-specific voucher types.
     */
    public function index()
    {
        $entityId = session('active_entity_id');
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Admin') || $user->roles->contains('id', 1);

        $voucherTypes = VoucherType::where(function ($q) use ($entityId) {
                $q->whereNull('entity_id')
                  ->orWhere('entity_id', $entityId);
            })
            ->orderBy('voucher_group', 'asc')
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
        $entityId = session('active_entity_id');
        $user = Auth::user();
        
        // Identify if user is Superadmin (role_id 1)
        // Usually checked via Spatie roles or EntityUser
        $isSuperAdmin = $user->hasRole('Admin') || $user->roles->contains('id', 1);

        $validated = $request->validate([
            'journal_name'        => [
                'required', 'string', 'max:100', 
                Rule::unique('mm_voucher_types')->where(fn ($q) => $q->where('entity_id', $entityId)->orWhereNull('entity_id'))
            ],
            'short_code'          => [
                'required', 'string', 'max:20', 
                Rule::unique('mm_voucher_types')->where(fn ($q) => $q->where('entity_id', $entityId)->orWhereNull('entity_id'))
            ],
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
            'entity_id'           => $createSystem ? null : $entityId,
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
        $entityId = session('active_entity_id');
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Admin') || $user->roles->contains('id', 1);

        $voucherType = VoucherType::where(function ($q) use ($entityId) {
                $q->whereNull('entity_id')
                  ->orWhere('entity_id', $entityId);
            })->findOrFail($id);

        // Protect system templates from non-admins
        if ($voucherType->entity_id === null && !$isSuperAdmin) {
            return response()->json([
                'message' => 'Global system templates can only be modified by Superadmins.',
            ], 403);
        }

        $validated = $request->validate([
            'journal_name' => [
                'required', 'string', 'max:100', 
                Rule::unique('mm_voucher_types')->ignore($id)->where(fn ($q) => $q->where('entity_id', $entityId)->orWhereNull('entity_id'))
            ],
            'short_code' => [
                'required', 'string', 'max:20', 
                Rule::unique('mm_voucher_types')->ignore($id)->where(fn ($q) => $q->where('entity_id', $entityId)->orWhereNull('entity_id'))
            ],
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

        $voucherType->update(array_merge($validated, [
            'entity_id' => ($isSuperAdmin && $validated['is_system_generated']) ? null : ($voucherType->entity_id ?? $entityId)
        ]));

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
        $entityId = session('active_entity_id');
        $voucherType = VoucherType::where('entity_id', $entityId)->findOrFail($id);
        
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
