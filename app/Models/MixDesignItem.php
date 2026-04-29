<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MixDesignItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_mix_design_items';

    protected $fillable = [
        'plant_id',
        'mix_design_id',
        'product_id',
        'uom_id',
        'rate',
        'actual_quantity',
        'cross_quantity',
        'variation_quantity',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'cross_quantity' => 'decimal:4',
        'variation_quantity' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }
}
