<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MachineLoan extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'mm_machine_loans';

    protected $fillable = [
        'machine_id',
        'loan_amount',
        'emi_amount',
        'tenure_months',
        'start_date',
        'end_date',
        'deleted_by',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function emiPayments()
    {
        return $this->hasMany(MachineEmiPayment::class, 'machine_loan_id');
    }
}
