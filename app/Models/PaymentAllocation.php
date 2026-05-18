<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditFields;

class PaymentAllocation extends Model
{
    use AuditFields;

    protected $table = 'mm_payment_allocations';

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount',
        'created_by',
        'updated_by'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
