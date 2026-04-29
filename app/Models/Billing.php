<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    protected $table = 'mm_billings';
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_id',
        'plant_id',
        'month',
        'total_amount',
        'breakdown_json',
        'status',
    ];

    protected $casts = [
        'breakdown_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
