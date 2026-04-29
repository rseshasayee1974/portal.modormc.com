<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AuditFields;

class PettyCash extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_petty_cash';

    protected $fillable = [
        'plant_id',
        'ref_no',
        'prefix',
        'date',
        'opening_balance',
        'closing_balance',
        'paid_by',
        'paid_to',
        'journal_status',
        'closed_status',
        'request_amount',
        'file',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'journal_status' => 'boolean',
        'closed_status' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->ref_no) {
                $model->ref_no = self::generateRefNo($model->plant_id);
            }
        });
    }

    public static function generateRefNo($plantId): string
    {
        $last = self::where('plant_id', $plantId)->orderBy('id', 'desc')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'PC-' . date('Ym') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recalculate closing balance from items.
     */
    public function recalculate(): void
    {
        $totalDebit  = $this->items()->sum('debit');
        $totalCredit = $this->items()->sum('credit');
        $this->closing_balance = $this->opening_balance - $totalDebit + $totalCredit;
        $this->saveQuietly();
    }

    public function items()
    {
        return $this->hasMany(PettyCashItem::class, 'petty_cash_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function paidToUser()
    {
        return $this->belongsTo(User::class, 'paid_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
