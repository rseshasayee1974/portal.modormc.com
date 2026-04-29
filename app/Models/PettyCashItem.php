<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class PettyCashItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_petty_cash_items';

    protected $fillable = [
        'plant_id',
        'petty_cash_id',
        'expense_id',
        'patron_id',
        'amount',
        'debit',
        'credit',
        'date',
        'description',
        'remarks',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(fn($m) => $m->created_by = Auth::id());
        static::updating(fn($m) => $m->updated_by = Auth::id());
        static::deleting(function ($m) {
            $m->deleted_by = Auth::id();
            $m->saveQuietly();
        });

        // After any item change, recalculate the parent's closing balance
        static::saved(fn($m) => $m->pettyCash?->recalculate());
        static::deleted(fn($m) => $m->pettyCash?->recalculate());
    }

    public function pettyCash()
    {
        return $this->belongsTo(PettyCash::class, 'petty_cash_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class, 'patron_id');
    }
}
