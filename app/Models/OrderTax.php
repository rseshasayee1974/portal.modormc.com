<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    /**
     * Direct relationship to parent Invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'order_id');
    }

    /**
     * Direct relationship to InvoiceItem.
     */
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'order_items_id');
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
    public static function createIntraStateSplit(Invoice $invoice, string $invoice_type, float $taxableAmount, float $fullRate, ?int $taxId = null, $orderItemsId = null): void
    {
        $tax = Tax::with('children')->find($taxId);
        $children = $tax ? $tax->children : collect();

        if ($children->isNotEmpty()) {
            foreach ($children as $child) {
                $accountId = $child->account_id;
                if (!$accountId) {
                    $accountId = self::getDefaultTaxAccountId($invoice, $invoice_type, $child);
                }

                self::create([
                    'order_type'     => $invoice_type,
                    'order_id'       => $invoice->id,
                    'plant_id'       => $invoice->plant_id,
                    'order_items_id' => $orderItemsId,
                    'account_id'     => $accountId,
                    'tax_id'         => $child->id,
                    'name'           => $child->tax_name,
                    'rate'           => $child->tax_rate,
                    'amount'         => round($taxableAmount * ($child->tax_rate / 100), 2),
                    'status'         => 1,
                ]);
            }
        } elseif ($tax) {
            // Fallback: Create a single split for the parent tax if no children exist
            $accountId = $tax->account_id;
            if (!$accountId) {
                $accountId = self::getDefaultTaxAccountId($invoice, $invoice_type, $tax);
            }

            self::create([
                'order_type'     => $invoice_type,
                'order_id'       => $invoice->id,
                'plant_id'       => $invoice->plant_id,
                'order_items_id' => $orderItemsId,
                'account_id'     => $accountId,
                'tax_id'         => $tax->id,
                'name'           => $tax->tax_name,
                'rate'           => $tax->tax_rate,
                'amount'         => round($taxableAmount * ($tax->tax_rate / 100), 2),
                'status'         => 1,
            ]);
        }
    }

    /**
     * Create IGST split (inter-state) for an invoice.
     */
    public static function createInterStateSplit(Invoice $invoice, string $invoice_type, float $taxableAmount, float $fullRate, ?int $taxId = null, $orderItemsId = null): void
    {
        $tax = Tax::with('children')->find($taxId);
        $children = $tax ? $tax->children : collect();

        if ($children->isNotEmpty()) {
            foreach ($children as $child) {
                $accountId = $child->account_id;
                if (!$accountId) {
                    $accountId = self::getDefaultTaxAccountId($invoice, $invoice_type, $child);
                }

                self::create([
                    'order_type'     => $invoice_type,
                    'order_id'       => $invoice->id,
                    'plant_id'       => $invoice->plant_id,
                    'order_items_id' => $orderItemsId,
                    'account_id'     => $accountId,
                    'tax_id'         => $child->id,
                    'name'           => $child->tax_name,
                    'rate'           => $child->tax_rate,
                    'amount'         => round($taxableAmount * ($child->tax_rate / 100), 2),
                    'status'         => 1,
                ]);
            }
        } elseif ($tax) {
            // Fallback: Create a single split for the parent tax if no children exist
            $accountId = $tax->account_id;
            if (!$accountId) {
                $accountId = self::getDefaultTaxAccountId($invoice, $invoice_type, $tax);
            }

            self::create([
                'order_type'     => $invoice_type,
                'order_id'       => $invoice->id,
                'plant_id'       => $invoice->plant_id,
                'order_items_id' => $orderItemsId,
                'account_id'     => $accountId,
                'tax_id'         => $tax->id,
                'name'           => $tax->tax_name,
                'rate'           => $tax->tax_rate,
                'amount'         => round($taxableAmount * ($tax->tax_rate / 100), 2),
                'status'         => 1,
            ]);
        }
    }

    /**
     * Resolve default tax ledger from Account Default Settings or fallbacks.
     */
    protected static function getDefaultTaxAccountId(Invoice $invoice, string $invoice_type, $tax): ?int
    {
        $taxName = strtolower($tax->tax_name ?? '');
        $isSales = in_array(strtolower($invoice_type), ['sales', 'invoice', 'dispatch']);
        
        $settingKey = null;
        $fallbackSearch = null;
        if (str_contains($taxName, 'cgst')) {
            $settingKey = $isSales ? 'cgst_output' : 'cgst_input';
            $fallbackSearch = 'CGST';
        } elseif (str_contains($taxName, 'sgst')) {
            $settingKey = $isSales ? 'sgst_output' : 'sgst_input';
            $fallbackSearch = 'SGST';
        } elseif (str_contains($taxName, 'igst')) {
            $settingKey = $isSales ? 'igst_output' : 'igst_input';
            $fallbackSearch = 'IGST';
        }

        if (!$settingKey) {
            return null;
        }

        $module = $isSales ? 'Invoice' : 'Purchase';
        $plantId = $invoice->plant_id;

        $mapped = \App\Models\AccountDefaultSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->where('setting_key', $settingKey)
            ->where('is_active', true)
            ->value('ledger_id');
            
        if ($mapped) return $mapped;

        return \App\Models\Ledger::where('title', 'like', "%{$fallbackSearch}%")
            ->where('plant_id', $plantId)
            ->value('id');
    }
}