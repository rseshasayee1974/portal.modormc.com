<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAudit extends Model
{
    protected $table = 'mm_payment_audit';

    protected $fillable = [
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
