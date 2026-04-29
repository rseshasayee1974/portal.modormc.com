<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSummary extends Model
{
    use HasFactory;
    protected $table = 'mm_usage_summaries';
    protected $fillable = [
        'user_id',
        'entity_id',
        'plant_id',
        'module',
        'date',
        'month',
        'tokens',
        'requests',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
