<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use App\Traits\TracksModelChanges;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, AuditFields , TracksModelChanges;
    protected $table = 'mm_sales_orders';
    protected $fillable = [
        'plant_id',
        'quotation_id',
        'patron_id',
        'site_id',
        'sales_executive_id',
        'order_date',
        'status',
        'sales_executive_id',
        'converted_by_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'sales_executive_id' => 'integer',
    ];
    protected $appends = ['has_workorders'];

    public function getHasWorkordersAttribute()
    {
        return $this->workOrders()->exists();
    }

    const STATUS_DRAFT = 0;
    const STATUS_CONFIRMED = 1;
    const STATUS_COMPLETED = 2;

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }


    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class);
    }

    public function converter()
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }

    public function salesExecutive()
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id', 'id');
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'sales_order_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }
}
