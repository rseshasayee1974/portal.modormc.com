<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderTax extends Model
{
    use HasFactory;

    protected $table = 'mm_order_taxes';

    protected $fillable = [
        'tax_id',
        'order_type',
        'order_id',
        'order_items_id',
        'account_id',
        'entity_id',
        'name',
        'rate',
        'amount',
        'status',
    ];

    protected $casts = [
        'rate'   => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    // ------------------------------------------------------------------ relations

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * Polymorphic: can belong to Invoice or InvoiceItem.
     */
    public function orderable()
    {
        return $this->morphTo(null, 'order_type', 'order_id');
    }

    // ------------------------------------------------------------------ static helpers

    /**
     * Create CGST + SGST split (intra-state) for an invoice.
     *
     * @param  Invoice  $invoice
     * @param  float    $taxableAmount
     * @param  float    $fullRate     e.g. 18 (will be split as 9+9)
     * @param  int|null $taxId
     */
    public static function createIntraStateSplit(Invoice $invoice, float $taxableAmount, float $fullRate, ?int $taxId = null): void
    {
        $half   = $fullRate / 2;
        $splits = [
            ['name' => 'CGST ' . $half . '%', 'rate' => $half],
            ['name' => 'SGST ' . $half . '%', 'rate' => $half],
        ];

        foreach ($splits as $split) {
            self::create([
                'order_type'  => 'Invoice',
                'order_id'    => $invoice->id,
                'tax_id'      => $taxId,
                'name'        => $split['name'],
                'rate'        => $split['rate'],
                'amount'      => round($taxableAmount * ($split['rate'] / 100), 2),
                'status'      => 1,
            ]);
        }
    }

    /**
     * Create IGST split (inter-state) for an invoice.
     */
    public static function createInterStateSplit(Invoice $invoice, float $taxableAmount, float $fullRate, ?int $taxId = null): void
    {
        self::create([
            'order_type' => 'Invoice',
            'order_id'   => $invoice->id,
            'tax_id'     => $taxId,
            'name'       => 'IGST ' . $fullRate . '%',
            'rate'       => $fullRate,
            'amount'     => round($taxableAmount * ($fullRate / 100), 2),
            'status'     => 1,
        ]);
    }
}
