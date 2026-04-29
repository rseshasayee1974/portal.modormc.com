<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dispatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_dispatches';

    protected $guarded = [];

    protected $casts = [
        'dispatch_time' => 'datetime',
        'delivered_qty' => 'decimal:3',
    ];

    /**
     * Relationships
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Patron::class, 'transport_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function mixDesign(): BelongsTo
    {
        return $this->belongsTo(MixDesign::class, 'mixdesign_id');
    }

    public function loadSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'load_site_id');
    }

    public function unloadSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'unload_site_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
    }

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'cleaner_id');
    }

    /**
     * Extended Data
     */
    public function weights(): HasOne
    {
        return $this->hasOne(DispatchWeight::class, 'dispatch_id');
    }

    public function financials(): HasOne
    {
        return $this->hasOne(DispatchFinancial::class, 'dispatch_id');
    }

    public function status(): HasOne
    {
        return $this->hasOne(DispatchStatus::class, 'dispatch_id');
    }
}
