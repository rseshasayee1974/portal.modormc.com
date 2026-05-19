<?php

return [
    'enabled' => env('AUDIT_LOGGING_ENABLED', true),

    'ignored_models' => [
        App\Models\ActivityLog::class,
    ],

    'ignored_routes' => [
        'ignition.*',
        'livewire.*',
        'sanctum.csrf-cookie',
    ],

    'ignored_fields' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'otp_secret',
        'api_key',
        'created_at',
        'updated_at',
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ],

    'status_fields' => [
        'status',
        'is_active',
        'is_suspended',
        'dispatch_status',
        'invoice_status',
        'payment_status',
        'receipt_status',
        'approval_status',
    ],

    'role_fields' => [
        'role_id',
        'roles',
    ],

    'permission_fields' => [
        'permission_id',
        'permissions',
    ],
];
