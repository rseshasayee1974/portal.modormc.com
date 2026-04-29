<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsageLog extends Model
{
    use HasFactory;

    protected $table = 'mm_api_usage_logs';

    protected $fillable = [
        'user_id',
        'entity_id',
        'plant_id',
        'module',
        'tokens_used',
        'endpoint',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
