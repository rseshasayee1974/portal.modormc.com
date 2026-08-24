<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TracksModelChanges;
class InvoiceItem extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_invoice_items';

    protected $fillable = [
        'invoice_id',
        'item_id',
        'uom_id',
        'item_name',
        'hsn_code',
        'tax_id',
        'quantity',
        'price_unit',
        'discount_type',
        'discount',
        'discount_amount',
        'subtotal',
        'line_tax_amount',
        'line_total',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['mix_design_id'];

    public function getMixDesignIdAttribute()
    {
        return $this->attributes['item_id'] ?? null;
    }

    protected $casts = [
        'item_id'         => 'integer',
        'tax_id'          => 'integer',
        'uom_id'          => 'integer',
        'quantity'        => 'decimal:2',
        'price_unit'      => 'decimal:2',
        'discount'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'line_tax_amount' => 'decimal:2',
        'line_total'      => 'decimal:2',
    ];

    // ------------------------------------------------------------------ boot
    protected static function boot(): void
    {
        parent::boot();

        // After any item change, cascade recalculate to parent invoice
        static::saved(fn($m) => $m->invoice?->recalculate());
        static::deleted(fn($m) => $m->invoice?->recalculate());
    }

    // ------------------------------------------------------------------ business logic

    /**
     * Compute all derived fields for a line item given a tax rate %.
     */
    public function compute(float $taxRate = 0): void
    {
        $gross = (float)$this->quantity * (float)$this->price_unit;

        // Discount
        $discountAmount = ($this->discount_type === '%')
            ? round($gross * ((float)$this->discount / 100), 2)
            : (float)$this->discount;

        $subtotal        = $gross - $discountAmount;
        $lineTaxAmount   = round($subtotal * ($taxRate / 100), 2);

        $this->discount_amount = $discountAmount;
        $this->subtotal        = $subtotal;
        $this->line_tax_amount = $lineTaxAmount;
        $this->line_total      = $subtotal + $lineTaxAmount;
    }

    // ------------------------------------------------------------------ relations
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    /**
     * Direct relationship to tax splits at line-item level.
     */
    public function orderTaxes()
    {
        return $this->hasMany(OrderTax::class, 'order_items_id', 'id')
            ->whereIn('order_type', ['Invoice', 'Purchase']);
    }

    /**
     * Direct line-item tax splits relationship.
     */
    public function itemTaxes()
    {
        return $this->hasMany(OrderTax::class, 'order_items_id', 'id')->where('order_type', 'Invoice');
    }
}
