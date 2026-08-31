<?php

namespace App\Models;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesOrderOperation extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;
    protected $table = 'mm_sales_order_operations';
    public $timestamps = false;

    protected $fillable = [
        'sales_order_id',
        'operation_name',
        'sequence',
        'duration',
        'status',
        'started_at',
        'completed_at',
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
    
    public function start()
    {
        $this->update(['status' => 2, 'started_at' => now()]);
    }

    public function complete()
    {
        $this->update(['status' => 3, 'completed_at' => now()]);
    }
}
