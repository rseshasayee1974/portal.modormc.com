<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use App\Traits\TracksModelChanges;

class SalesOrderItem extends Model
{
    use HasFactory, SoftDeletes, AuditFields, TracksModelChanges;

    protected $table = 'mm_sales_order_items';

    protected $fillable = [
        'sales_order_id',
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

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
