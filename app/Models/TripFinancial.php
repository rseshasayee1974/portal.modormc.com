<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripFinancial extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mm_trip_financials';
    protected $fillable = [
        'trip_id',
        'product_units',
        'product_amount',
        'product_tax_id',
        'product_tax_amount',
        'updated_product_amount',
        'updated_tax_id',
        'updated_tax_rate',
        'updated_at_time',
        'update_reference',
        'unload_units',
        'unload_total_amount',
        'transport_rate',
        'transport_unit',
        'transport_tax_id',
        'transport_tax_rate',
        'transport_reference',
        'pass_amount',
        'discount_amount',
        'transport_expenses',
        'cost_of_product',
        'round_off',
    ];

    protected $casts = [
        'product_amount'       => 'decimal:2',
        'product_tax_amount'   => 'decimal:2',
        'updated_product_amount' => 'decimal:2',
        'unload_total_amount'  => 'decimal:2',
        'transport_rate'       => 'decimal:2',
        'pass_amount'          => 'decimal:2',
        'discount_amount'      => 'decimal:2',
        'transport_expenses'   => 'decimal:2',
        'cost_of_product'      => 'decimal:2',
        'updated_at_time'      => 'datetime'
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function productTax()
    {
        return $this->belongsTo(Tax::class, 'product_tax_id');
    }

    public function transportTax()
    {
        return $this->belongsTo(Tax::class, 'transport_tax_id');
    }

    /**
     * Compute Revenue: (Product Units * Product Rate) + Tax + Transport components.
     */
    public function getGrossRevenueAttribute(): float
    {
        $productBase = $this->updated_product_amount > 0 ? $this->updated_product_amount : $this->product_amount;
        $taxAddon    = ($productBase * ($this->updated_tax_rate ?: ($this->productTax?->rate ?: 0))) / 100;
        
        $transportBase = ($this->transport_unit || 1) * $this->transport_rate;
        $transportTax  = ($transportBase * ($this->transport_tax_rate ?: 0)) / 100;

        return (float)($productBase + $taxAddon + $transportBase + $transportTax + $this->pass_amount - $this->discount_amount);
    }

    /**
     * Compute Trip Costs: Product COGS + Transport Expenses (Fuel, Batch, etc).
     */
    public function getGrossCostAttribute(): float
    {
        return (float)(($this->cost_of_product ?: 0) + $this->transport_expenses);
    }

    /**
     * Net Margin per trip.
     */
    public function getTripProfitAttribute(): float
    {
        return $this->gross_revenue - $this->gross_cost;
    }
}
