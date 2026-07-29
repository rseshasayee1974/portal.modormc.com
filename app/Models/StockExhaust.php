<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class StockExhaust extends Model
{
    use SoftDeletes, HasFactory, PlantScoping;

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
        'prefix',
        'reference_number',
        'ledger_id',
        'created_by',
        'modified_by',
        'deleted_by',
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

            if (empty($model->reference_number)) {
                $ref = self::generateReference($model->plant_id, $model->prefix);
                $model->prefix = $ref['prefix'];
                $model->reference_number = $ref['reference_number'];
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->modified_by = Auth::id();
            }
        });
    }

    public static function generateReference($plantId, $customPrefix = null)
    {
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
        
        if (empty($customPrefix)) {
            $customPrefix = 'SE';
        }
        
        $prefix = "{$customPrefix}-{$fyString}-";

        $latest = self::where('plant_id', $plantId)
                      ->where('prefix', $prefix)
                      ->orderBy('id', 'desc')
                      ->value('reference_number');
                      
        $sequence = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return [
            'prefix' => $prefix,
            'reference_number' => sprintf('%s%04d', $prefix, $sequence)
        ];
    }

    public function partner()
    {
        return $this->belongsTo(Patron::class, 'partner_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function lines()
    {
        return $this->hasMany(StockExhaustLine::class, 'stock_id');
    }
}
