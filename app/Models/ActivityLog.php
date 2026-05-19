<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $casts = [
        'user_id' => 'int',
        'plant_id' => 'int',
        'old_values' => 'json',
        'new_values' => 'json',
        'changed_fields' => 'json',
        'response_status' => 'int',
        'metadata' => 'json',
    ];

    protected $fillable = [
        'user_id',
        'plant_id',
        'module_name',
        'entity_type',
        'entity_id',
        'action_type',
        'old_values',
        'new_values',
        'changed_fields',
        'description',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'operating_system',
        'request_method',
        'request_url',
        'route_name',
        'response_status',
        'trace_id',
        'metadata',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForEntity($query, string $entityType, string|int $entityId)
    {
        return $query
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId);
    }
}
