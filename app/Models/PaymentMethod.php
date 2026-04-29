<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    protected $table = 'mm_payment_methods';
    use HasFactory;

    protected $fillable = ['name'];

    public function payments()
    {
        return $this->hasMany(TripPayment::class);
    }
}
