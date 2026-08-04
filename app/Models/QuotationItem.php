<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TracksModelChanges;

class QuotationItem extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;
    protected $table = 'mm_quotation_items';

    protected $appends = [
        'is_in_use',
    ];
    
    protected $fillable = [
        'quotation_id',
        'mix_design_id',
        'uom_id',
        'quantity',
        'rate',
        'tax_id',
        'tax_amount',
        'untaxed_amount',
        'amount_total',
    ];

    /**
     * Sync pump rates for this item.
     * Upserts each pump type, removes any types not in $pumpRates.
     */
    public function syncPumpRates(array $pumpRates): void
    {
        $keepTypes = [];
        foreach ($pumpRates as $pr) {
            if (empty($pr['pump_type'])) continue;
            $rate = (float)($pr['pump_rate'] ?? 0);
            
            $keepTypes[] = $pr['pump_type'];
            $this->pumpRates()->updateOrCreate(
                ['pump_type' => $pr['pump_type']],
                [
                    'pump_rate' => $rate,
                    'quotation_id' => $this->quotation_id,
                ]
            );
        }
        // Remove stale or zero entries
        if (!empty($keepTypes)) {
            $stale = $this->pumpRates()->whereNotIn('pump_type', $keepTypes)->get();
            foreach ($stale as $item) {
                $item->delete();
            }
        } else {
            $stale = $this->pumpRates()->get();
            foreach ($stale as $item) {
                $item->delete();
            }
        }
    }

    protected static function booted()
    {
        static::deleting(function ($item) {
            foreach ($item->pumpRates as $pr) {
                $pr->delete();
            }
        });
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }
    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
    public function pumpRates()
    {
        return $this->hasMany(QuotationItemPumpRate::class, 'quotation_item_id');
    }

    public function getIsInUseAttribute(): bool
    {
        return $this->quotation()->exists();
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }
}
