<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_payment_methods';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(DispatchPayment::class, 'payment_method_id');
    }

    public function tripPayments()
    {
        return $this->hasMany(PaymentMethod::class, 'payment_method_id');
    }
}
