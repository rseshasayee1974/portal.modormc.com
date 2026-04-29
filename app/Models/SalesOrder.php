<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    protected $table = 'mm_sales_orders';
    protected $fillable = [
        'plant_id',
        'quotation_id',
        'patron_id',
        'site_id',
        'order_date',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    const STATUS_DRAFT = 0;
    const STATUS_CONFIRMED = 1;
    const STATUS_PARTIAL_DISPATCH = 2;
    const STATUS_COMPLETED = 3;

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
}
