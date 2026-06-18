<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\AuditFields;
use App\Traits\PostsToAccounting;
use App\Traits\PlantScoping;
use App\Models\JournalEntry;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PostsToAccounting, PlantScoping;

    protected $table = 'mm_invoices';

    protected $fillable = [
        'plant_id', 'partner_id', 'account_id', 
        'invoice_type', 'invoice_label', 'ref_id', 'ref_title',  
        'invoice_number', 'prefix', 'invoice_date', 'due_date', 'period',
        'subtotal', 'global_discount_type', 'global_discount', 'tax_amount', 'adjustment',
        'shipping_charges', 'shipping_tax_id',
        'total_amount', 'round_off', 'tds_amount', 'tds_tax_id',
        'paid_amount', 'balance_amount',
        'status', 'einvoice_status', 'is_duplicate', 'is_sent', 'is_reconciled',
        'is_active',
        'einvoice_irn', 'einvoice_ack_no', 'einvoice_ack_date', 'einvoice_qr_code',
        'eway_bill_no', 'eway_bill_date', 'eway_bill_valid_until',
        'created_by', 'updated_by',
    ];

    protected $appends = ['encrypted_id', 'full_number'];

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function getFullNumberAttribute()
    {
        return ($this->prefix ?? '') . ($this->invoice_number ?? '');
    }

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
        'einvoice_ack_date' => 'datetime',
        'eway_bill_date' => 'datetime',
        'eway_bill_valid_until' => 'datetime',
    ];

    // ------------------------------------------------------------------ constants
    const STATUS_DRAFT     = 'draft';
    const STATUS_APPROVED  = 'approved';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    public static array $statuses = ['draft', 'approved', 'paid', 'cancelled'];

    // ------------------------------------------------------------------ boot / audit
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($m) {
            if (empty($m->invoice_number)) {
                $details = self::generateNumber($m->plant_id, $m->invoice_label ?? $m->invoice_type, $m->account_id);
                $m->prefix = $details['prefix'];
                $m->invoice_number = $details['next_number'];
            }
            $m->adjustment = $m->adjustment ?? 0;
            $m->shipping_charges = $m->shipping_charges ?? 0;
            $m->round_off = $m->round_off ?? 0;
        });

        static::deleted(function ($m) {
            // Use withTrashed() and handle multiple possible ref_module values for backward compatibility
            JournalEntry::withTrashed()
                ->whereIn('ref_module', ['invoice', 'bill'])
                ->where('ref_id', $m->id)
                ->get()
                ->each(function($entry) {
                    // Rename voucher number to avoid unique constraint violations on regeneration
                    // and mark as deleted for audit purposes.
                    $entry->updateQuietly([
                        'voucher_number' => $entry->voucher_number . '/VOID/' . $entry->id,
                        'is_deleted'     => 1,
                        'deleted_by'     => auth()->id(),
                        'deleted_at'     => now(),
                    ]);

                    // Also update lines
                    $entry->lines()->withTrashed()->update([
                        'is_deleted' => 1,
                        'deleted_by' => auth()->id(),
                        'deleted_at' => now(),
                    ]);
                                        $entry->delete();
                });

            // Cascading soft deletes for invoice components
            $m->items->each->delete();
            $m->orderTaxes()->delete();

            // Reverse Purchase Order Billed Status if this was a bill generated from PO
            if ($m->invoice_type === 'bill' && $m->ref_id) {
                $poIds = explode(',', $m->ref_id);
                \App\Models\PurchaseOrder::whereIn('id', $poIds)->update([
                    'invoice_status' => 0,
                    'billing_id'     => null,
                    'billing_status' => 'Pending',
                    'journal_status' => '0',
                    'billed_date'    => null,
                ]);
            }
            // Reverse Dispatch Status if applicable
            $dispatches = \App\Models\Dispatch::whereHas('status', function ($q) use ($m) {
                $q->where('invoice_id', $m->id);
            })->get();
            foreach ($dispatches as $dispatch) {
                $dispatch->resetInvoice();
            }
        });
    }

    // ------------------------------------------------------------------ business logic

    /**
     * Auto-generate invoice number: INV-YYYYMM-0001
     */
    public static function generateNumber(int $plantId, ?string $label = 'sales', ?int $accountId = null): array
    {
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $endYear = $startYear + 1;
        $fy = substr($startYear, -2) . substr($endYear, -2);

        $normalizedLabel = strtolower($label);
        $query = self::query()->where('plant_id', $plantId);

        if ($normalizedLabel === 'purchase' || $normalizedLabel === 'bill') {
             $query->where(function($q) {
                 $q->where('invoice_type', 'bill')->orWhere('invoice_label', 'purchase');
             });
             $prefix = "Bill/{$fy}/";
        } elseif ($normalizedLabel === 'batching' || $normalizedLabel === 'dispatch') {
             $query->where('invoice_label', 'Dispatch');
             $prefix = "Inv/{$fy}/";
        } else {
             $query->where('invoice_type', 'sales')->whereNull('invoice_label');
             $prefix = "INV/{$fy}/";
        }

        // if ($accountId) {
        //      $prefix .= "{$accountId}/";
        //      $query->where('account_id', $accountId);
        // }
            
        $lastInvoice = $query->where('prefix', $prefix)
            ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
            ->first();

        $next = $lastInvoice ? ((int)$lastInvoice->invoice_number + 1) : 1;

        return [
            'prefix' => $prefix,
            'next_number' => (string)$next,
            'full_number' => $prefix . $next
        ];
    }

    /**
     * Recompute all invoice totals from lines and persist.
     * Called after any item create / update / delete.
     */
    public function recalculate(): void
    {
        $items = $this->items()->withTrashed(false)->get();

        $subtotal = $items->sum('subtotal');
        $itemDiscountTotal = $items->sum('discount_amount');
        
        $globalDiscount = 0;
        if ($this->global_discount_type === '%') {
            $globalDiscount = $subtotal * (($this->global_discount ?? 0) / 100);
        } else {
            $globalDiscount = $this->global_discount ?? 0;
        }

        $discountTotal  = $itemDiscountTotal + $globalDiscount;
        $taxAmount      = $items->sum('line_tax_amount');
        
        // Add shipping charges to total
        $rawTotal       = $subtotal + $taxAmount - $globalDiscount + $this->adjustment + ($this->shipping_charges ?? 0);
        $rounded        = round($rawTotal);
        $roundOff       = $rounded - $rawTotal;

        $this->updateQuietly([
            'subtotal'       => $subtotal,
            'global_discount' => $discountTotal,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $rounded,
            'round_off'      => $roundOff,
            'balance_amount' => max(0, $rounded - (float)($this->paid_amount ?? 0)),
        ]);
    }

    /**
     * Transition the invoice to a new status with guard.
     */
    public function transitionTo(string $status): void
    {
        $allowed = [
            self::STATUS_DRAFT     => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED  => [self::STATUS_PAID, self::STATUS_CANCELLED],
            self::STATUS_PAID      => [],
            self::STATUS_CANCELLED => [],
        ];

        throw_unless(
            in_array($status, $allowed[$this->status] ?? []),
            \InvalidArgumentException::class,
            "Cannot transition invoice from [{$this->status}] to [{$status}]."
        );

        $this->update(['status' => $status]);
    }

    // ------------------------------------------------------------------ relations
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Direct relationship to tax splits at invoice level.
     */
    public function orderTaxes()
    {
        return $this->hasMany(OrderTax::class, 'order_id')
            ->whereIn('order_type', ['Invoice', 'Purchase']);
    }

    public function partner()
    {
        return $this->belongsTo(Patron::class, 'partner_id');
    }

    public function vendor() { return $this->partner(); }
    public function customer() { return $this->partner(); }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function account()
    {
        return $this->belongsTo(Ledger::class, 'account_id');
    }

    // public function journal()
    // {
    //     return $this->belongsTo(JournalEntry::class, 'journal_id');
    // }

    // public function truck()
    // {
    //     return $this->belongsTo(Machine::class, 'truck_id');
    // }

    public function shippingTax()
    {
        return $this->belongsTo(Tax::class, 'shipping_tax_id');
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Create invoice and its items in a transaction.
     */
    public static function createWithItems(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            $invoice = self::create($data);

            foreach ($itemsData as $itemData) {
                $itemTaxRate = 0;
                if (!empty($itemData['tax_id'])) {
                    $tax = Tax::find($itemData['tax_id']);
                    $itemTaxRate = $tax?->rate ?? 0;
                }

                $item = new InvoiceItem($itemData);
                $item->invoice_id = $invoice->id;
                $item->compute($itemTaxRate);
                $item->save();
            }

            $invoice->refresh();
            $invoice->recalculate();
            
            // For now, we still sync aggregated splits at invoice level using a weighted average or just the first item's rate for split naming
            // In a full implementation, we might want per-item splits, but let's stick to the invoice level for now as per syncTaxSplits signature.
            $invoice->syncTaxSplits($invoice['invoice_type']);

            return $invoice;
        });
    }

    /**
     * Update invoice and sync its items.
     */
    public function updateWithItems(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $itemsData = $data['items'] ?? [];
            unset($data['items']);

            $this->update($data);

            $keptIds = collect($itemsData)->pluck('id')->filter()->toArray();
            // Use get() then delete() to ensure model events (AuditFields) are triggered
            $this->items()->whereNotIn('id', $keptIds)->get()->each->delete();

            foreach ($itemsData as $itemData) {
                $itemTaxRate = 0;
                if (!empty($itemData['tax_id'])) {
                    $tax = Tax::find($itemData['tax_id']);
                    $itemTaxRate = $tax?->rate ?? 0;
                }

                if (!empty($itemData['id'])) {
                    $item = InvoiceItem::find($itemData['id']);
                    $item->fill($itemData);
                } else {
                    $item = new InvoiceItem($itemData);
                    $item->invoice_id = $this->id;
                }
                $item->compute($itemTaxRate);
                $item->save();
            }

            $this->refresh();
            $this->recalculate();
            $this->syncTaxSplits($this['invoice_type']);

            return $this;
        });
    }

    /**
     * Sync CGST/SGST/IGST splits at invoice level.
     */
    public function syncTaxSplits(string $invoice_type = 'Invoice'): void
    {
        $normalizedType = in_array(strtolower($invoice_type), ['bill', 'purchase']) ? 'Purchase' : 'Invoice';
        $this->orderTaxes()->delete();
        
        // 1. Process line items individually to capture specific item IDs and accounts
        foreach ($this->items()->with('tax')->get() as $item) {
            if (!$item->tax_id || $item->line_tax_amount <= 0) continue;

            $tax = $item->tax;
            $fullRate = $tax ? $tax->tax_rate : 0;
            if ($fullRate <= 0) continue;
            $tax_group = $tax->tax_group;
            $plantAddr   = $this->plant?->addresses()?->first();
            $partnerAddr = $this->partner?->addresses()?->first();
           
            $plantState   = $plantAddr?->state?->state_code ?? $plantAddr?->state_code;
            $partnerState = $partnerAddr?->state?->state_code ?? $partnerAddr?->state_code;

            if ($tax_group == 'GST') {
                OrderTax::createIntraStateSplit($this, $normalizedType, $item->subtotal, $fullRate, $item->tax_id,   $item->id);
            } else {
                OrderTax::createInterStateSplit($this, $normalizedType, $item->subtotal, $fullRate, $item->tax_id,    $item->id);
            }
        }

        // // 2. Handle shipping tax split if applicable
        // if ($this->shipping_charges > 0 && $this->shipping_tax_id) {
        //     $shippingTax = Tax::find($this->shipping_tax_id);
        //     if ($shippingTax && $shippingTax->tax_rate > 0) {
        //         $plantState   = $this->plant?->addresses()?->first()?->state?->state_code;
        //         $partnerState = $this->partner?->addresses()?->first()?->state?->state_code;

        //         if ($plantState && $partnerState && $plantState === $partnerState) {
        //             OrderTax::createIntraStateSplit($this, $this->shipping_charges, $shippingTax->tax_rate, $this->shipping_tax_id, $shippingTax->account_id);
        //         } else {
        //             OrderTax::createInterStateSplit($this, $this->shipping_charges, $shippingTax->tax_rate, $this->shipping_tax_id, $shippingTax->account_id);
        //         }
        //     }
        // }
    }

    /**
     * Globally common function to generate an Invoice from a source document (PO, SO, etc.)
     */
    public static function createFromSource($source, string $type, array $params = []): self
    {
        return DB::transaction(function () use ($source, $type, $params) {
            $plantId = $params['plant_id'] ?? session('active_plant_id');
            $userId  = auth()->id();

            $subtotalSum = 0;
            $taxSum = 0;
            $discountSum = 0;
            
            $itemsData = [];
            foreach ($source->items as $item) {
                if ($type === 'bill') {
                    // For a Purchase Bill, quantity is the received/invoiced quantity
                    $qty = (float) ($item->invoiced_quantity > 0 ? $item->invoiced_quantity : ($item->received_quantity > 0 ? $item->received_quantity : $item->received_quantity));
                    $priceUnit = (float) data_get($item, 'unit_price');
                     $item_id = data_get($item, 'product_id');
                    // Recalculate discount
                    $discountType = data_get($item, 'discount_type');
                    $discountVal = (float) data_get($item, 'discount_amount');
                    $lineSubtotalBeforeDiscount = $qty * $priceUnit;
                    
                    if ($discountType === 'percentage') {
                        $lineDiscount = ($lineSubtotalBeforeDiscount * $discountVal) / 100;
                    } else {
                        // Scale the fixed discount proportionally if quantity changed from ordered quantity
                        $orderedQty = (float) data_get($item, 'product_quantity');
                        if ($qty == $orderedQty || $orderedQty == 0) {
                            $lineDiscount = $discountVal;
                        } else {
                            $lineDiscount = ($discountVal / $orderedQty) * $qty;
                        }
                    }
                    
                    $lineSubtotal = $lineSubtotalBeforeDiscount - $lineDiscount;
                    
                    // Recalculate tax
                    $taxRate = 0;
                    $taxId = data_get($item, 'tax_id');
                    if ($taxId) {
                        $tax = \App\Models\Tax::find($taxId);
                        if ($tax) {
                            $taxRate = (float) $tax->tax_rate;
                        }
                    }
                    
                    $lineTax = ($lineSubtotal * $taxRate) / 100;
                    $lineTotal = $lineSubtotal + $lineTax;
                } else {
                    // For sales/invoice, use original values
                    $item_id = data_get($item, 'mix_design_id');
                    $qty = (float) (data_get($item, 'product_quantity') ?? data_get($item, 'quantity') ?? 0);
                    $priceUnit = (float) data_get($item, 'unit_price');
                    $lineDiscount = (float) data_get($item, 'total_discount');
                    $lineSubtotal = (float) data_get($item, 'price_subtotal');
                    $lineTax = (float) data_get($item, 'price_tax');
                    $lineTotal = (float) data_get($item, 'price_total');
                    $taxId = data_get($item, 'tax_id');
                }
                
                $subtotalSum += $lineSubtotal;
                $taxSum += $lineTax;
                $discountSum += $lineDiscount;
                
                $itemsData[] = [
                    'item_id'         => $item_id,
                    'item_name'       => data_get($item, 'product.title') ?? data_get($item, 'description'),
                    'hsn_code'        => data_get($item, 'product.hsn_code'),
                    'quantity'        => $qty,
                    'uom_id'          => data_get($item, 'product_uom') ?? data_get($item, 'uom_id'),
                    'price_unit'      => $priceUnit,
                    'discount_type'   => data_get($item, 'discount_type'),
                    'discount'        => data_get($item, 'discount_amount'),
                    'discount_amount' => $lineDiscount,
                    'subtotal'        => $lineSubtotal,
                    'line_tax_amount' => $lineTax,
                    'line_total'      => $lineTotal,
                    'tax_id'          => $taxId,
                ];
            }

            $subtotal = $type === 'bill' ? ($subtotalSum - $source->discount_amount) : $source->amount_untaxed;
            $discountTotal = $type === 'bill' ? ($discountSum + $source->discount_amount) : $source->discount_amount;
            $taxAmount = $type === 'bill' ? $taxSum : $source->amount_tax;
            
            $adjustment = $source->adjustment;
            $shippingCharges = $source->shipping_charges;
            
            if ($type === 'bill') {
                $totalAmount = $subtotal + $taxAmount + $shippingCharges + $adjustment;
                $roundedTotal = round($totalAmount);
                $roundOff = $roundedTotal - $totalAmount;
                $totalAmount = $roundedTotal;
            } else {
                $roundOff = $source->rounding_value;
                $totalAmount = $source->amount_total;
            }

            // 1. Create the Invoice Header
            $invoice = self::create([
                'plant_id'         => $plantId,
                'partner_id'       => $params['partner_id'] ?? ($type === 'bill' ? $source->vendor_id : $source->customer_id),
                'account_id'       => $params['account_id'] ?? null,
                'invoice_type'     => $type,
                'invoice_label'    => $params['invoice_label'] ?? null,
                'ref_id'           => $source->id,
                'ref_title'        => null,
                'invoice_date'     => $params['invoice_date'] ?? now(),
                'due_date'         => $params['due_date'] ?? $source->due_date,
                'subtotal'         => $subtotal,
                'global_discount'  => $discountTotal,
                'tax_amount'       => $taxAmount,
                'adjustment'       => $adjustment,
                'shipping_charges' => $shippingCharges,
                'round_off'        => $roundOff,
                'total_amount'     => $totalAmount,
                'balance_amount'   => $totalAmount,
                'status'           => self::STATUS_APPROVED,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);
 
            // 2. Create Invoice Items
            foreach ($itemsData as $itemData) {
                $invoice->items()->create($itemData);
            }

            // 3. Sync Tax Splits (Generates mm_order_taxes records)
            $invoice->syncTaxSplits();

            // 4. Automated Accounting Posting
            if ($invoice->status === self::STATUS_APPROVED || $invoice->status === self::STATUS_PAID) {
                $invoice->postToAccounting();
            }

           

            return $invoice;
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        try {
            $decrypted = decrypt($value);
            return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $decrypted)->first();
        } catch (\Exception $e) {
            return parent::resolveRouteBinding($value, $field);
        }
    }
}