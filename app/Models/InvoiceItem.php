<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_invoice_items';

    protected $fillable = [
        'invoice_id',
        'item_name',
        'hsn_code',
        'quantity',
        'price_unit',
        'discount_type',
        'discount',
        'discount_amount',
        'subtotal',
        'line_tax_amount',
        'line_total',
    ];

    protected $casts = [
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
        $gross = $this->quantity * $this->price_unit;

        // Discount
        $discountAmount = $this->discount_type === 'fixed'
            ? $this->discount
            : round($gross * ($this->discount / 100), 2);

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

    /**
     * Polymorphic tax splits at line-item level.
     */
    public function orderTaxes()
    {
        return $this->morphMany(OrderTax::class, 'order', 'order_type', 'order_id');
    }
}
