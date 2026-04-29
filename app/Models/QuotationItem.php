<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class QuotationItem extends Model
{
    use HasFactory, SoftDeletes, AuditFields;
    protected $table = 'mm_quotation_items';
    protected $fillable = [
        'quotation_id',
        'mix_design_id',
        'uom_id',
        'quantity',
        'rate',
        'tax_id',
        'tax_amount',
        'untaxed_amount',
        'amount_total',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function mixDesign()
    {
        return $this->belongsTo(MixDesign::class, 'mix_design_id');
    }
}
