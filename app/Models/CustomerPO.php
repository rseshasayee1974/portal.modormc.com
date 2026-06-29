<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use App\Traits\TracksModelChanges;

class CustomerPO extends Model
{
    use HasFactory, SoftDeletes, AuditFields, TracksModelChanges;

    /** Override auto-derived module name (Str::snake('CustomerPO') produces 'customer_p_o') */
    public static string $permissionModule = 'customer_po';

    protected $table = 'mm_customer_pos';

    protected $fillable = [
        'plant_id',
        'quotation_id',
        'patron_id',
        'site_id',
        'sales_executive_id',
        'concrete_pump',
        'order_date',
        'status',
        'converted_by_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'sales_executive_id' => 'integer',
    ];

    protected $appends = ['has_salesorders'];

    public function getHasSalesordersAttribute()
    {
        return $this->salesOrders()->exists();
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
        return $this->hasMany(Dispatch::class, 'customer_po_id');
    }

    public function converter()
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }

    public function salesExecutive()
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id', 'id');
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_po_id');
    }

    public function items()
    {
        return $this->hasMany(CustomerPOItem::class, 'customer_po_id');
    }
}
