<?php

namespace App\Models;

use App\Traits\AuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkOrderItem extends Model
{
    use HasFactory, AuditFields, SoftDeletes;
    protected $table = 'mm_work_order_items';
    public $timestamps = false; // Using custom audit fields

    protected $fillable = [
        'work_order_id',
        'material_id',
        'required_qty',
        'issued_qty',
        'uom_id',
        'location_id',
        'status',
        'created_by',
        'created',
        'modified',
        'updated_by',
        'deleted_by'
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function material()
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    // Business Logic
    public function issueMaterial($qty)
    {
        $this->issued_qty += $qty;
        $this->save();
    }
}
