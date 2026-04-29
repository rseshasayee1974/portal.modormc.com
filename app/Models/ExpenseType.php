<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class ExpenseType extends Model
{
	protected $table = 'mm_expense_types';
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plant_id',
        'name',
        'ledger_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->created_by = Auth::id() ?? 1;
        });

        static::updating(function ($model) {
            $model->updated_by = Auth::id() ?? 1;
        });

        static::deleting(function ($model) {
            $model->deleted_by = Auth::id() ?? 1;
            $model->save();
        });
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }
}
