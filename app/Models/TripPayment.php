<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripPayment extends Model
{
    use HasFactory;
    protected $table = 'mm_trip_payments';
    protected $fillable = [
        'trip_id',
        'payment_method_id',
        'amount',
        'payment_type',
        'party_id',
        'reference',
        'collected_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class, 'party_id');
    }
}
