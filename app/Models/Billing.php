<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\TracksModelChanges;
class Billing extends Model
{
    use TracksModelChanges;

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

    public function destroyer()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
