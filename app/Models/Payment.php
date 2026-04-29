<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class Payment extends Model
{
	protected $table = 'mm_payments';
    use HasFactory, SoftDeletes, AuditFields;

    protected $fillable = [
        'plant_id',
        'transaction_date',
        'ledger_id',
        'patron_id',
        'partner_type',
        'amount',
        'excess_amount',
        'use_excess_amount',
        'transaction_type',
        'transaction_mode',
        'reconcile_opening_balance',
        'batch_deposit',
        'description',
        'reference',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'excess_amount' => 'decimal:2',
        'use_excess_amount' => 'boolean',
        'reconcile_opening_balance' => 'boolean',
        'batch_deposit' => 'boolean',
        'transaction_date' => 'date:Y-m-d'
    ];

    protected static function boot()
    {
        parent::boot();
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Complete the payment transaction. State transition.
     */
    public function transitionToCompleted(): void
    {
        if ($this->status !== 'completed') {
            $this->status = 'completed';
            $this->save();
        }
    }

    public function transitionToFailed(): void
    {
        if ($this->status !== 'failed') {
            $this->status = 'failed';
            $this->save();
        }
    }
}
