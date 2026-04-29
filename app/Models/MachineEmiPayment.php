<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MachineEmiPayment extends Model
{
    use HasFactory;

    protected $table = 'mm_machine_emi_payments';

    protected $fillable = [
        'machine_loan_id',
        'due_date',
        'paid_date',
        'amount',
        'status',
    ];

    public function loan()
    {
        return $this->belongsTo(MachineLoan::class, 'machine_loan_id');
    }
}
