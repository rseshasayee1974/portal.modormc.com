<?php

/*
 * Author: Cipher
 * Created: 2026-11-11 12:55:08 Asia/Calcutta (UTC+05:30)
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $fillable = [
        'name',
        'email',
        'issue_type',
        'subject',
        'message',
        'app_version',
        'device_details',
        'steps_to_reproduce',
        'status',
        'notification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_sent_at' => 'datetime',
        ];
    }
}
