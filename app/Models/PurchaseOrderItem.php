<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_purchase_order_items';

    protected $fillable = [
        'plant_id',
        'order_id',
        'product_id',
        'product_uom',
        'tax_id',
        'product_quantity',
        'unit_price',
        'description',
        'price_subtotal',
        'price_total',
        'price_tax',
        'hsn_code',
        'discount_type',
        'discount_amount',
        'total_discount',
        'invoiced_quantity',
        'received_quantity',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'product_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'price_subtotal' => 'decimal:2',
        'price_total' => 'decimal:2',
        'price_tax' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'invoiced_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'status' => 'integer',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'product_uom');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history()
    {
        return $this->hasMany(PurchaseOrderHistory::class, 'order_item_id');
    }

    /**
     * Calculate item totals.
     */
    public function calculateItemTotals()
    {
        $qty = (float) $this->product_quantity;
        $price = (float) $this->unit_price;
        $subtotal = $qty * $price;
        
        $discountAmount = (float) $this->discount_amount;
        if ($this->discount_type === 'percentage') {
             $calculatedDiscount = ($subtotal * $discountAmount) / 100;
        } else {
             $calculatedDiscount = $discountAmount;
        }
        
        $this->total_discount = $calculatedDiscount;
        $this->price_subtotal = $subtotal - $calculatedDiscount;
        
        // Ensure tax is loaded or fetched
        $tax = $this->tax;
        if (!$tax && $this->tax_id) {
            $tax = Tax::find($this->tax_id);
        }

        if ($tax) {
            $this->price_tax = ($this->price_subtotal * (float) $tax->tax_rate) / 100;
        } else {
            $this->price_tax = 0;
        }
        
        $this->price_total = $this->price_subtotal + $this->price_tax;
        $this->save();
    }
}
