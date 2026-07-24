<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\PlantScoping;

use App\Traits\TracksModelChanges;
class PaymentTransaction extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_payment_transactions';

    protected $fillable = [
        'plant_id',
        'payment_id',
        'origin',
        'origin_id',
        'ledger_id',
        'patron_id',
        'debit_amount',
        'credit_amount',
        'transaction_date',
        'reference',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'transaction_date' => 'date'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }
}
