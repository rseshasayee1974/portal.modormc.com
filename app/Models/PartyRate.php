<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use Illuminate\Support\Facades\Auth;

class PartyRate extends Model
{
    protected $table = 'mm_party_rates';
    use HasFactory, SoftDeletes, AuditFields;

    protected $fillable = [
        'plant_id',
        'patron_id',
        'loading_site',
        'unloading_site',
        'uom_id',
        'payment_type',
        'product_id',
        'product_rate',
        'transport_rate',
        'rate',
        'status',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at'
    ];

    protected $casts = [
        'product_rate'   => 'decimal:2',
        'transport_rate' => 'decimal:2',
        'rate'           => 'decimal:2',
        'status'         => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->calculateTotalRate();
        });

        static::updating(function ($model) {
            $model->calculateTotalRate();
        });
    }

    /**
     * Compute total rate. Either it's aggregated from product + transport rates,
     * or it's a direct override total rate. Falls back properly.
     */
    public function calculateTotalRate(): void
    {
        // If they provided breakdown components but didn't explicitly override the total, compute it.
        if (($this->product_rate ?? 0) > 0 || ($this->transport_rate ?? 0) > 0) {
            $this->rate = (float)($this->product_rate ?? 0.0) + (float)($this->transport_rate ?? 0.0);
        }
    }

    // ------------------------------------------------------------------ Relations

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function loadingSite()
    {
        return $this->belongsTo(Site::class, 'loading_site');
    }

    public function unloadingSite()
    {
        return $this->belongsTo(Site::class, 'unloading_site');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
