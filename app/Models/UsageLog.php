<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsageLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_usage_logs';

    protected $casts = [
        'entity_id' => 'int',
        'used_count' => 'int',
    ];

    protected $fillable = [
        'entity_id',
        'feature_code',
        'used_count',
        'period',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }
}
