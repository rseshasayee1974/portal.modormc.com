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
        'concrete_pump',
        'pump_rate',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected static function booted()
    {
        //
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