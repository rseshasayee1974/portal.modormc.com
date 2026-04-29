<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchMaterial extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_batch_materials';

    protected $fillable = [
        'batch_id',
        'product_id',
        'material_name',
        'target_qty',
        'actual_qty',
        'deviation_quantity',
        'uom_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'target_qty' => 'decimal:3',
        'actual_qty' => 'decimal:3',
        'deviation_quantity' => 'decimal:3',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
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
