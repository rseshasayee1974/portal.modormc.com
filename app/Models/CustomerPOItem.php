<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TracksModelChanges;

class CustomerPOItem extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_customer_po_items';

    protected $fillable = [
        'customer_po_id',
        'mix_design_id',
        'quantity',
        'rate',
        'tax_id',
        'tax_amount',
        'untaxed_amount',
        'amount_total',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Sync pump rates for this CPO item.
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
                    'customer_po_id' => $this->customer_po_id,
                ]
            );
        }
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

    public function pumpRates()
    {
        return $this->hasMany(CustomerPOItemPumpRate::class, 'customer_po_item_id');
    }

    public function customerPO()
    {
        return $this->belongsTo(CustomerPO::class, 'customer_po_id');
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }
    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
