<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_journal_entries';

    protected $fillable = [
        'entity_id',
        'plant_id',
        'voucher_type',
        'voucher_number',
        'ref_module',
        'ref_name',
        'ref_id',
        'voucher_date',
        'posting_date',
        'narration',
        'total_debit',
        'total_credit',
        'is_status',
        'reversal_of_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_deleted',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'posting_date' => 'date',
        'total_debit'  => 'decimal:4',
        'total_credit' => 'decimal:4',
        'is_deleted'   => 'boolean',
    ];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBalanced()
    {
        return $this->total_debit === $this->total_credit;
    }
}
