<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DispatchStatus extends Model
{
        use HasFactory;

    protected $table = 'mm_dispatch_statuses';

    protected $guarded = [];

    protected $casts = [
        'is_tax_inclusive' => 'boolean',
        'invoice_date' => 'date',
        'transport_rate' => 'decimal:2',
        'transport_tax_rate' => 'decimal:2',
        'transport_tax_amount' => 'decimal:2',
        'transport_total_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'transport_km' => 'decimal:2',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function transportTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'transport_tax_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
