<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use App\Traits\TracksModelChanges;

class CustomerPOItemPumpRate extends Model
{
    use HasFactory, SoftDeletes, AuditFields, TracksModelChanges;

    protected $table = 'mm_customer_po_item_pump_rates';

    protected $fillable = [
        'customer_po_id',
        'customer_po_item_id',
        'pump_type',
        'pump_rate',
    ];

    protected $casts = [
        'pump_rate' => 'decimal:2',
    ];

    public function customerPO()
    {
        return $this->belongsTo(CustomerPO::class, 'customer_po_id');
    }

    public function customerPOItem()
    {
        return $this->belongsTo(CustomerPOItem::class, 'customer_po_item_id');
    }

    public function pump()
    {
        return $this->belongsTo(Machine::class, 'pump_type');
    }
}
