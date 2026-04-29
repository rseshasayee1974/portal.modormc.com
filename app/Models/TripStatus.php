<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripStatus extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_trip_statuses';
    protected $fillable = [
        'trip_id',
        'trip_status',
        'is_closed',
        'invoice_status',
        'transport_bill_status',
        'purchase_bill_status',
        'driver_salary_status',
        'cleaner_salary_status',
        'is_load_tax_inclusive',
        'is_unload_tax_inclusive',
        'invoice_id',
        'invoice_date',
        'invoice_number',
        'purchase_date',
        'purchase_invoice_number',
        'transport_date',
        'transport_invoice_number',
        'transport_km',
    ];

    protected $casts = [
        'is_closed'              => 'boolean',
        'is_load_tax_inclusive'  => 'boolean',
        'is_unload_tax_inclusive' => 'boolean',
        'invoice_date'           => 'date',
        'purchase_date'          => 'date',
        'transport_date'         => 'date',
        'transport_km'           => 'decimal:2',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
