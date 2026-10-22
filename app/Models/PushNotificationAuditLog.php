<?php

/*
Author: Cipher
Created: 2026-10-17 14:44:50 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationAuditLog extends Model
{
    protected $table = 'mm_push_notification_audit_logs';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'log_from' => 'array', 'log_to' => 'array', 'provider_response' => 'array',
        'attempt_number' => 'integer', 'created_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(PushNotificationLog::class, 'push_notification_log_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
