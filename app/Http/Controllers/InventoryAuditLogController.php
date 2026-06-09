<?php

namespace App\Http\Controllers;

use App\Models\InventoryAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryAuditLogController extends Controller
{
    /**
     * Display a listing of the inventory audit logs.
     */
    public function index(Request $request)
    {
        $query = InventoryAuditLog::query()
            ->with(['user:id,username,email', 'plant:id,name'])
            ->latest();

        // Apply filters
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->filled('reference_id')) {
            $query->where('reference_id', $request->reference_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)
            ->withQueryString()
            ->through(fn (InventoryAuditLog $log) => [
                'id' => $log->id,
                'plant_id' => $log->plant_id,
                'plant' => $log->plant ? [
                    'id' => $log->plant->id,
                    'name' => $log->plant->name,
                ] : null,
                'transaction_type' => $log->transaction_type,
                'reference_type' => $log->reference_type,
                'reference_id' => $log->reference_id,
                'log_from' => $log->log_from,
                'log_to' => $log->log_to,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->username,
                    'email' => $log->user->email,
                ] : null,
                'remarks' => $log->remarks,
                'ip_address' => $log->ip_address,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ]);

        // Get unique transaction types for filter dropdown
        $transactionTypes = InventoryAuditLog::select('transaction_type')
            ->distinct()
            ->pluck('transaction_type');

        // Get unique reference types for filter dropdown
        $referenceTypes = InventoryAuditLog::select('reference_type')
            ->distinct()
            ->whereNotNull('reference_type')
            ->pluck('reference_type');

        // Fetch users for filtering dropdown
        $users = User::orderBy('username')
            ->get(['id', 'username', 'email'])
            ->map(fn (User $user) => [
                'id'    => $user->id,
                'label' => "{$user->username} ({$user->email})",
            ]);

        // return json_encode($logs);
        return Inertia::render('InventoryAuditLogs/Index', [
            'logs' => $logs,
            'transactionTypes' => $transactionTypes,
            'referenceTypes' => $referenceTypes,
            'users' => $users,
            'filters' => $request->only([
                'transaction_type',
                'reference_type',
                'reference_id',
                'user_id',
                'date_from',
                'date_to'
            ]),
        ]);
    }

    /**
     * Store a newly created inventory audit log in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_type' => 'required|string|max:255',
            'reference_type' => 'nullable|string|max:255',
            'reference_id' => 'nullable|integer',
            'log_from' => 'required',
            'log_to' => 'required',
            'remarks' => 'nullable|string',
        ]);

        $log = InventoryAuditLog::create(array_merge($validated, [
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
        ]));

        return response()->json([
            'success' => true,
            'log' => $log
        ], 201);
    }

    /**
     * Display the specified inventory audit log.
     */
    public function show(InventoryAuditLog $inventoryAuditLog)
    {
        $inventoryAuditLog->load(['user:id,username,email', 'plant:id,name', 'reference']);

        return Inertia::render('InventoryAuditLogs/Show', [
            'log' => [
                'id' => $inventoryAuditLog->id,
                'plant_id' => $inventoryAuditLog->plant_id,
                'plant' => $inventoryAuditLog->plant ? [
                    'id' => $inventoryAuditLog->plant->id,
                    'name' => $inventoryAuditLog->plant->name,
                ] : null,
                'transaction_type' => $inventoryAuditLog->transaction_type,
                'reference_type' => $inventoryAuditLog->reference_type,
                'reference_id' => $inventoryAuditLog->reference_id,
                'reference' => $inventoryAuditLog->reference,
                'log_from' => $inventoryAuditLog->log_from,
                'log_to' => $inventoryAuditLog->log_to,
                'user' => $inventoryAuditLog->user ? [
                    'id' => $inventoryAuditLog->user->id,
                    'name' => $inventoryAuditLog->user->username,
                    'email' => $inventoryAuditLog->user->email,
                ] : null,
                'remarks' => $inventoryAuditLog->remarks,
                'ip_address' => $inventoryAuditLog->ip_address,
                'created_at' => optional($inventoryAuditLog->created_at)->toIso8601String(),
            ]
        ]);
    }
}
