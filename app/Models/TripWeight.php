<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripWeight extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_trip_weights';
    protected $fillable = [
        'trip_id',
        'empty_weight_load',
        'loaded_weight_load',
        'empty_weight_image',
        'loaded_weight_image',
        'empty_weight_time',
        'loaded_weight_time',
        'empty_weight_unload',
        'loaded_weight_unload',
        'round_off',
    ];

    protected $casts = [
        'empty_weight_time'  => 'datetime',
        'loaded_weight_time' => 'datetime',
        'empty_weight_load'  => 'decimal:2',
        'loaded_weight_load' => 'decimal:2',
        'empty_weight_unload' => 'decimal:2',
        'loaded_weight_unload' => 'decimal:2',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Compute current net weight at loading point.
     */
    public function getNetWeightLoadAttribute(): float
    {
        return (float)($this->loaded_weight_load - $this->empty_weight_load);
    }

    /**
     * Compute current net weight at unloading point.
     */
    public function getNetWeightUnloadAttribute(): float
    {
        return (float)($this->loaded_weight_unload - $this->empty_weight_unload);
    }
}
