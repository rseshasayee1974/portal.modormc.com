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
        'load_rate' => 'decimal:2',
        'load_tax_amount' => 'decimal:2',
        'load_untax_amount' => 'decimal:2',
        'load_total_amount' => 'decimal:2',
        'pass_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'transport_expenses' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'transport_rate' => 'decimal:2',
        'transport_tax_rate' => 'decimal:2',
        'transport_tax_amount' => 'decimal:2',
        'transport_total_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
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

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
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

    public function status(): HasOne
    {
        return $this->hasOne(DispatchStatus::class, 'dispatch_id');
    }
}
