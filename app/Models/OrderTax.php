<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Models\Tax;

class OrderTax extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mm_order_taxes';

    protected $fillable = [
        'tax_id',
        'plant_id',
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
     * @param  int|null $accountId
     * @param  int|null $orderItemsId
     */
    public static function createIntraStateSplit(Invoice $invoice, float $taxableAmount, float $fullRate, ?int $taxId = null, $orderItemsId = null): void
    {
        $tax = Tax::with('children')->find($taxId);
        $children = $tax ? $tax->children : collect();

        if ($children->isNotEmpty()) {
            foreach ($children as $child) {
                self::create([
                    'order_type'     => 'Invoice',
                    'order_id'       => $invoice->id,
                    'plant_id'       => $invoice->plant_id,
                    'order_items_id' => $orderItemsId,
                    'account_id'     => $child->account_id,
                    'tax_id'         => $child->id,
                    'name'           => $child->tax_name,
                    'rate'           => $child->tax_rate,
                    'amount'         => round($taxableAmount * ($child->tax_rate / 100), 2),
                    'status'         => 1,
                ]);
            }
        }
    }

    /**
     * Create IGST split (inter-state) for an invoice.
     */
    public static function createInterStateSplit(Invoice $invoice, float $taxableAmount, float $fullRate, ?int $taxId = null, $orderItemsId = null): void
    {
        $tax = Tax::with('children')->find($taxId);
        $children = $tax ? $tax->children : collect();

        if ($children->isNotEmpty()) {
            foreach ($children as $child) {
                self::create([
                    'order_type'     => 'Invoice',
                    'order_id'       => $invoice->id,
                    'plant_id'       => $invoice->plant_id,
                    'order_items_id' => $orderItemsId,
                    'account_id'     => $child->account_id,
                    'tax_id'         => $child->id,
                    'name'           => $child->tax_name,
                    'rate'           => $child->tax_rate,
                    'amount'         => round($taxableAmount * ($child->tax_rate / 100), 2),
                    'status'         => 1,
                ]);
            }
        }
    }
}
