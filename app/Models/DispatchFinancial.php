<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchFinancial extends Model
{
    use HasFactory;

    protected $table = 'mm_dispatch_financials';

    protected $guarded = [];

    protected $casts = [
        'unload_at_time' => 'datetime',
        'load_rate' => 'decimal:2',
        'load_amount' => 'decimal:2',
        'load_tax_rate' => 'decimal:2',
        'load_tax_amount' => 'decimal:2',
        'load_total_amount' => 'decimal:2',
        'unload_rate' => 'decimal:2',
        'unload_amount' => 'decimal:2',
        'unload_tax_rate' => 'decimal:2',
        'unload_tax_amount' => 'decimal:2',
        'unload_total_amount' => 'decimal:2',
        'transport_rate' => 'decimal:2',
        'transport_amount' => 'decimal:2',
        'transport_tax_rate' => 'decimal:2',
        'transport_tax_amount' => 'decimal:2',
        'transport_total_amount' => 'decimal:2',
        'pass_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'transport_expenses' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'untaxed_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function loadTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'load_tax_id');
    }

    public function unloadTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'unload_tax_id');
    }

    public function transportTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'transport_tax_id');
    }
}
