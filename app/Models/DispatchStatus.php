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
        'is_closed' => 'boolean',
        'is_load_tax_inclusive' => 'boolean',
        'is_unload_tax_inclusive' => 'boolean',
        'invoice_date' => 'date',
        'transport_date' => 'date',
        'transport_km' => 'decimal:2',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
