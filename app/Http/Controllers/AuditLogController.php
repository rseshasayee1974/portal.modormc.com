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

        $logs = ActivityLog::query()
            ->with('user:id,username,email')
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id'               => $log->id,
                'user'             => $log->user ? [
                    'id'    => $log->user->id,
                    'name'  => $log->user->username,
                    'email' => $log->user->email,
                ] : null,
                'module_name'      => $log->module_name,
                'entity_type'      => $log->entity_type,
                'entity_id'        => $log->entity_id,
                'action_type'      => $log->action_type,
                'description'      => $log->description,
                'trace_id'         => $log->trace_id,
                'request_method'   => $log->request_method,
                'request_url'      => $log->request_url,
                'response_status'  => $log->response_status,
                'ip_address'       => $log->ip_address,
                'browser'          => $log->browser,
                'operating_system' => $log->operating_system,
                'device_type'      => $log->device_type,
                'old_values'       => $log->old_values,
                'new_values'       => $log->new_values,
                'changed_fields'   => $log->changed_fields,
                'metadata'         => $log->metadata,
                'created_at'       => optional($log->created_at)?->toIso8601String(),
            ]);

        $actionTypes = [
            'CREATE', 'UPDATE', 'DELETE', 'SOFT_DELETE', 'RESTORE',
            'LOGIN', 'LOGOUT', 'PASSWORD_CHANGE', 'STATUS_CHANGE',
            'ROLE_CHANGE', 'PERMISSION_CHANGE', 'EXPORT', 'IMPORT',
            'UPLOAD', 'DOWNLOAD', 'PAYMENT', 'APPROVE', 'REJECT',
            'ASSIGN', 'UNASSIGN', 'API_CALL', 'SYSTEM_EVENT',
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
                'id'    => $user->id,
                'label' => "{$user->username} ({$user->email})",
            ]);

        return Inertia::render('AuditLogs/Index', [
            'logs'        => $logs,
            'actionTypes' => $actionTypes,
            'modules'     => $modules,
            'users'       => $users,
            'filters'     => [
                'search'      => $request->input('search', ''),
                'action_type' => $request->input('action_type', ''),
                'date_from'   => $request->input('date_from', ''),
                'date_to'     => $request->input('date_to', ''),
                'entity_type' => $request->input('entity_type', ''),
                'module_name' => $request->input('module_name', ''),
                'trace_id'    => $request->input('trace_id', ''),
                'user_id'     => $request->input('user_id', ''),
            ],
        ]);
    }
}
