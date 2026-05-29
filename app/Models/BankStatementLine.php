<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\PlantScoping;

class BankStatementLine extends Model
{
    use HasFactory, SoftDeletes, PlantScoping;

    protected $table = 'mm_bank_statement_lines';

    protected $fillable = [
        'plant_id',
        'bank_ledger_id',
        'transaction_date',
        'value_date',
        'description',
        'reference_no',
        'debit_amount',
        'credit_amount',
        'balance',
        'reconciled_line_id',
        'reconciled_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'balance' => 'decimal:4',
        'reconciled_at' => 'datetime',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function bankLedger()
    {
        return $this->belongsTo(Ledger::class, 'bank_ledger_id');
    }

    public function reconciledJournalLine()
    {
        return $this->belongsTo(JournalEntryLine::class, 'reconciled_line_id');
    }
}
