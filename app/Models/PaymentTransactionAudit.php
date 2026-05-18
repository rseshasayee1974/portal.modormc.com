<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransactionAudit extends Model
{
    protected $table = 'mm_payment_transaction_audit';

    protected $fillable = [
        'payment_transaction_id',
        'payment_id',
        'data',
        'action',
        'action_by'
    ];

    protected $casts = [
        'data' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
