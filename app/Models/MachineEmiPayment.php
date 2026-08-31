<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MachineEmiPayment extends Model
{
    use SoftDeletes, HasFactory, TracksModelChanges;

    protected $table = 'mm_machine_emi_payments';

    protected $fillable = [
        'machine_loan_id',
        'due_date',
        'paid_date',
        'amount',
        'status',
        'deleted_by',
    ];

    public function loan()
    {
        return $this->belongsTo(MachineLoan::class, 'machine_loan_id');
    }
}
