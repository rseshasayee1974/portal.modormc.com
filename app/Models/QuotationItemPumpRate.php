<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;
use App\Traits\TracksModelChanges;

class QuotationItemPumpRate extends Model
{
    use HasFactory, SoftDeletes, AuditFields, TracksModelChanges;

    protected $table = 'mm_quotation_item_pump_rates';

    protected $fillable = [
        'quotation_id',
        'quotation_item_id',
        'pump_type',
        'pump_rate',
    ];

    protected $casts = [
        'pump_rate' => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function quotationItem()
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }
}
