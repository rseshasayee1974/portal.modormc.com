<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TracksModelChanges;
class JournalEntryLine extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'plant_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'cost_center_id',
        'partner_type',
        'partner_id',
        'tax_id',
        'narration_name',
        'narration_label',
        'line_narration',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_deleted',
    ];

    protected $casts = [
        'debit_amount'  => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'is_deleted'    => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($line) {
            if (!$line->isForceDeleting()) {
                $line->updateQuietly([
                    'is_deleted' => 1,
                    'deleted_by' => auth()->id()
                ]);
            }
        });
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'account_id');
    }

    public function partner()
    {
        return $this->belongsTo(Patron::class, 'partner_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function isDebit()
    {
        return $this->debit_amount > 0;
    }
}
