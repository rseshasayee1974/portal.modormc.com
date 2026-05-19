<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isSystemAdmin(), 403);

        $filters = [
            'search' => $request->string('search')->toString(),
            'action_type' => $request->string('action_type')->toString(),
            'module_name' => $request->string('module_name')->toString(),
            'user_id' => $request->integer('user_id') ?: null,
            'entity_type' => $request->string('entity_type')->toString(),
            'trace_id' => $request->string('trace_id')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];

        $logs = ActivityLog::query()
            ->with('user:id,username,email')
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhere('entity_id', 'like', "%{$search}%")
                        ->orWhere('request_url', 'like', "%{$search}%");
                });
            })
            ->when($filters['action_type'], fn ($query, $value) => $query->where('action_type', $value))
            ->when($filters['module_name'], fn ($query, $value) => $query->where('module_name', $value))
            ->when($filters['user_id'], fn ($query, $value) => $query->where('user_id', $value))
            ->when($filters['entity_type'], fn ($query, $value) => $query->where('entity_type', $value))
            ->when($filters['trace_id'], fn ($query, $value) => $query->where('trace_id', $value))
            ->when($filters['date_from'], fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'], fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->username,
                    'email' => $log->user->email,
                ] : null,
                'module_name' => $log->module_name,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'action_type' => $log->action_type,
                'description' => $log->description,
                'trace_id' => $log->trace_id,
                'request_method' => $log->request_method,
                'request_url' => $log->request_url,
                'response_status' => $log->response_status,
                'ip_address' => $log->ip_address,
                'browser' => $log->browser,
                'operating_system' => $log->operating_system,
                'device_type' => $log->device_type,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'changed_fields' => $log->changed_fields,
                'metadata' => $log->metadata,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ]);

        $actionTypes = [
            'CREATE',
            'UPDATE',
            'DELETE',
            'SOFT_DELETE',
            'RESTORE',
            'LOGIN',
            'LOGOUT',
            'PASSWORD_CHANGE',
            'STATUS_CHANGE',
            'ROLE_CHANGE',
            'PERMISSION_CHANGE',
            'EXPORT',
            'IMPORT',
            'UPLOAD',
            'DOWNLOAD',
            'PAYMENT',
            'APPROVE',
            'REJECT',
            'ASSIGN',
            'UNASSIGN',
            'API_CALL',
            'SYSTEM_EVENT',
        ];

        $modules = ActivityLog::query()
            ->select('module_name')
            ->distinct()
            ->orderBy('module_name')
            ->pluck('module_name')
            ->values();

        $users = User::query()
            ->orderBy('username')
            ->limit(100)
            ->get(['id', 'username', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => "{$user->username} ({$user->email})",
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'filters' => $filters,
                'logs' => $logs,
            ]);
        }

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'actionTypes' => $actionTypes,
            'modules' => $modules,
            'users' => $users,
        ]);
    }
}
