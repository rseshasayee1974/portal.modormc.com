<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class Quantity extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_quantity';

    protected $fillable = [
        'plant_id',  
        'uom_id', 
        'product_id',
        'opening_quantity', 
        'quantity', 
        'date',
        'is_warehouse', 
        'status',
        'created_by', 
        'updated_by', 
        'deleted_by',
    ];

    protected $casts = [
        'is_warehouse' => 'boolean',
        'quantity'     => 'decimal:2',
        'opening_quantity' => 'decimal:2',
        'date'         => 'date',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }
}
