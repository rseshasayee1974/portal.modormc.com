<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class Trip extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    protected $table = 'mm_trips';
    protected $fillable = [
        'trip_type',
        'reference_id',
        'truck_id',
        'truck_model',
        'party_id',
        'vendor_id',
        'transport_id',
        'load_site_id',
        'unload_site_id',
        'product_id',
        'driver_id',
        'cleaner_id',
        'maistry_id',
        'loader_id',
        'operator_id',
        'payment_mode',
        'plant_id',
        'entity_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Eager load standard relations.
     */
    protected $with = ['weights', 'financials', 'status'];

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function party()
    {
        return $this->belongsTo(Patron::class, 'party_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Patron::class, 'vendor_id');
    }

    public function transport()
    {
        return $this->belongsTo(Transport::class);
    }

    public function loadSite()
    {
        return $this->belongsTo(Site::class, 'load_site_id');
    }

    public function unloadSite()
    {
        return $this->belongsTo(Site::class, 'unload_site_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Personnel
    public function driver() { return $this->belongsTo(Personnel::class, 'driver_id'); }
    public function cleaner() { return $this->belongsTo(Personnel::class, 'cleaner_id'); }
    public function maistry() { return $this->belongsTo(Personnel::class, 'maistry_id'); }
    public function loader() { return $this->belongsTo(Personnel::class, 'loader_id'); }
    public function operator() { return $this->belongsTo(Personnel::class, 'operator_id'); }

    // Child Aspects
    public function weights() { return $this->hasOne(TripWeight::class); }
    public function financials() { return $this->hasOne(TripFinancial::class); }
    public function status() { return $this->hasOne(TripStatus::class); }
    public function payments() { return $this->hasMany(TripPayment::class); }

    /**
     * Compute current outstanding amount for this trip.
     */
    public function getBalanceAmountAttribute(): float
    {
        $revenue = $this->financials?->gross_revenue ?? 0;
        $paid    = $this->payments()->sum('amount');
        return (float)($revenue - $paid);
    }

    /**
     * Summary for Dashboarding.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'revenue' => $this->financials?->gross_revenue ?? 0,
            'cost'    => $this->financials?->gross_cost ?? 0,
            'profit'  => $this->financials?->trip_profit ?? 0,
            'balance' => $this->balance_amount,
            'status'  => $this->status?->trip_status ?? 0,
        ];
    }
}
