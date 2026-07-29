<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class StockExhaustLine extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'mm_stock_exhaust_lines';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'modified';

    protected $fillable = [
        'stock_id',
        'product_id',
        'issue_date',
        'quantity_issued',
        'no_items_issued',
        'units',
        'issued_to',
        'vehicle_no',
        'changed_km',
        'notes',
        'created_by',
        'modified_by',
        'deleted_by',
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
        'quantity_issued' => 'decimal:2',
        'no_items_issued' => 'decimal:2',
        'changed_km' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->modified_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->modified_by = Auth::id();
            }
        });
    }

    public function stockExhaust()
    {
        return $this->belongsTo(StockExhaust::class, 'stock_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Machine::class, 'vehicle_no');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
