<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

class PurchaseOrderHistory extends Model
{
    use HasFactory, SoftDeletes, \App\Traits\AuditFields;

    protected $table = 'mm_purchase_order_history';

    protected $fillable = [
        'plant_id', 'order_id', 'order_item_id', 'received_date',
        'product_id', 'uom_id', 'used_quantity', 'received_qty',
        'unit_price', 'count_quantity', 'inward_no', 'status',
        'truck_id', 'truck_loaded', 'truck_empty',
        'created_by', 'updated_by', 'deleted_by'
    ];

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function item()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    public static function generateNextInwardNo($plantId, $date = null)
    {
        $finYearString = PurchaseOrder::getFinancialYearString($date);
        $prefix = "INW-" . $finYearString . "-";

        $lastRecord = self::where('plant_id', $plantId)
            ->where('inward_no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastRecord && preg_match('/' . preg_quote($prefix, '/') . '(\d+)/i', $lastRecord->inward_no, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s%04d', $prefix, $nextNumber);
    }
}
