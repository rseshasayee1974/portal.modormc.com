<?php

namespace App\Models;

use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDiscount extends Model
{
    use PlantScoping, TracksModelChanges;

    protected $table = 'mm_account_discount';
    public const UPDATED_AT = 'modified_at';
    protected $fillable = [
        'primary_type', 'value_type', 'value', 'amount', 'journal_id', 'account_id',
        'partner_id', 'move_id', 'date', 'note', 'status',
    ];
    protected $casts = ['value' => 'decimal:2', 'amount' => 'decimal:2', 'date' => 'date:Y-m-d', 'status' => 'integer'];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'account_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Patron::class, 'partner_id')->withoutGlobalScope('active_operational_status');
    }
}
