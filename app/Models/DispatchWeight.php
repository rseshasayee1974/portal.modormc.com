<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchWeight extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_dispatch_weights';

    protected $guarded = [];

    protected $casts = [
        'empty_weight_time_load' => 'datetime',
        'loaded_weight_time_load' => 'datetime',
        'empty_weight_time_unload' => 'datetime',
        'loaded_weight_time_unload' => 'datetime',
        'empty_weight_truck' => 'decimal:2',
        'loaded_weight_truck' => 'decimal:2',
        'empty_weight_unload' => 'decimal:2',
        'loaded_weight_unload' => 'decimal:2',
        'round_off' => 'decimal:2',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }
}
