<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class DispatchPayment extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_dispatch_payments';

    protected $fillable = [
        'dispatch_id',
        'payment_method_id',
        'amount',
        'payment_type',
        'party_id',
        'reference',
        'is_active',
        'collected_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function party()
    {
        return $this->belongsTo(Patron::class, 'party_id');
    }
}
