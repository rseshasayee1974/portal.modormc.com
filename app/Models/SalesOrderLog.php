<?php

namespace App\Models;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLog extends Model
{
    
        use TracksModelChanges;
protected $table = 'mm_sales_order_logs';
    public $timestamps = false;

    protected $fillable = [
        'sales_order_id',
        'status',
        'remarks',
        'changed_by',
        'changed_at',
        'created_by',
        'created',
        'modified',
        'updated_by',
        'deleted_by'
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
