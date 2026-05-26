<?php

namespace App\Models;

use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class StockExhaust extends Model
{
    use HasFactory, PlantScoping;

    protected $table = 'mm_stock_exhaust';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'modified';

    protected $fillable = [
        'partner_id',
        'name',
        'bill_number',
        'billed_date',
        'invoice_status',
        'status',
        'issued_date',
        'plant_id',
        'created_by',
        'modified_by'
    ];

    protected $casts = [
        'billed_date' => 'date',
        'issued_date' => 'date',
        'created' => 'datetime',
        'modified' => 'datetime',
        'invoice_status' => 'integer',
        'status' => 'integer',
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

    public function partner()
    {
        return $this->belongsTo(Patron::class, 'partner_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function lines()
    {
        return $this->hasMany(StockExhaustLine::class, 'stock_id');
    }
}
