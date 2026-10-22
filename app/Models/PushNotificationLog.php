<?php



namespace App\Models;

use App\Observers\PushNotificationAuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PushNotificationLog extends Model
{
    use SoftDeletes;

    protected $table = 'mm_push_notification_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array', 'provider_response' => 'array', 'attempts' => 'integer',
        'sent_at' => 'datetime', 'last_attempt_at' => 'datetime', 'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime', 'read_at' => 'datetime',
        'created_by' => 'integer', 'updated_by' => 'integer', 'deleted_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::observe(PushNotificationAuditObserver::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(PushNotificationAuditLog::class)->orderBy('id');
    }
}
