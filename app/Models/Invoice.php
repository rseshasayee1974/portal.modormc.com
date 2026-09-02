<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\PostsToAccounting;
use App\Traits\PlantScoping;
use App\Models\JournalEntry;
use App\Contracts\Postable;
use App\DTO\TaxLineDTO;
use App\DTO\AdjustmentLineDTO;
use App\Accounting\AccountingPostingService;
use App\Accounting\LedgerResolver;
use App\Models\AccountDefaultSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use App\Traits\TracksModelChanges;
class Invoice extends Model implements Postable
{
        use HasFactory, SoftDeletes, PostsToAccounting, PlantScoping, TracksModelChanges;

    protected $table = 'mm_invoices';

    protected $fillable = [
        'plant_id', 'partner_id', 'account_id', 
        'invoice_type', 'invoice_label', 'ref_id', 'ref_title',  
        'invoice_number', 'prefix', 'invoice_date', 'due_date', 'period',
        'subtotal', 'global_discount_type', 'global_discount', 'discount_total', 'tax_amount', 'adjustment',
        'shipping_charges', 'shipping_tax_id',
        'total_amount', 'round_off', 'tds_amount', 'tds_tax_id',
        'paid_amount', 'balance_amount',
        'status', 'is_duplicate', 'is_sent', 'is_reconciled',
        'is_active', 'notes',
        'created_by', 'updated_by',
    ];

    protected $appends = ['encrypted_id', 'full_number', 'einvoice_irn', 'einvoice_ack_no', 'einvoice_ack_date', 'einvoice_qr_code', 'einvoice_status', 'eway_bill_no', 'eway_bill_date', 'eway_bill_valid_until'];

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function getFullNumberAttribute()
    {
        return ($this->prefix ?? '') . ($this->invoice_number ?? '');
    }

    public function getEinvoiceIrnAttribute()
    {
        return $this->einvoiceRelation?->einv_irn ?? $this->attributes['einvoice_irn'] ?? null;
    }

    public function getEinvoiceAckNoAttribute()
    {
        return $this->einvoiceRelation?->einv_ackno ?? $this->attributes['einvoice_ack_no'] ?? null;
    }

    public function getEinvoiceAckDateAttribute()
    {
        return $this->einvoiceRelation?->einv_ack_date ?? $this->attributes['einvoice_ack_date'] ?? null;
    }

    public function getEinvoiceQrCodeAttribute()
    {
        return $this->einvoiceRelation?->einv_signed_qrcode ?? $this->attributes['einvoice_qr_code'] ?? null;
    }

    public function getEinvoiceStatusAttribute()
    {
        return $this->einvoiceRelation?->einv_status ?? $this->attributes['einvoice_status'] ?? null;
    }

    public function getEwayBillNoAttribute()
    {
        return $this->ewaybillDetail?->ewaybill_no ?? $this->attributes['eway_bill_no'] ?? null;
    }

    public function getEwayBillDateAttribute()
    {
        return $this->ewaybillDetail?->ewaybill_date ?? $this->attributes['eway_bill_date'] ?? null;
    }

    public function getEwayBillValidUntilAttribute()
    {
        return $this->ewaybillDetail?->valid_upto ?? $this->attributes['eway_bill_valid_until'] ?? null;
    }

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
    ];

    // ------------------------------------------------------------------ constants
    const STATUS_DRAFT     = 'Draft';
    const STATUS_APPROVED  = 'Approved';
    const STATUS_PAID      = 'Paid';
    const STATUS_CANCELLED = 'Cancelled';

    public static array $statuses = ['Draft', 'Approved', 'Paid', 'Cancelled'];

    // ------------------------------------------------------------------ boot / audit
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($m) {
            if (empty($m->prefix) || empty($m->invoice_number)) {
                $details = self::generateNumber($m->plant_id, $m->invoice_label ?? $m->invoice_type, $m->account_id);
                if (empty($m->prefix)) {
                    $m->prefix = $details['prefix'];
                }
                if (empty($m->invoice_number)) {
                    $m->invoice_number = $details['next_number'];
                }
            }
            if (!empty($m->prefix) && !empty($m->invoice_number) && str_starts_with((string)$m->invoice_number, $m->prefix)) {
                $m->invoice_number = substr($m->invoice_number, strlen($m->prefix));
            }

            // Enforce duplicate restriction plant-wide for prefix + invoice_number
            if (!empty($m->prefix) && !empty($m->invoice_number) && !empty($m->plant_id)) {
                $fullNumber = ($m->prefix ?? '') . ($m->invoice_number ?? '');
                $alreadyExists = self::withoutGlobalScopes()
                    ->where('plant_id', $m->plant_id)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($m, $fullNumber) {
                        $q->where(function ($sub) use ($m) {
                            $sub->where('prefix', $m->prefix)
                                ->where('invoice_number', $m->invoice_number);
                        })
                        ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(prefix, ''), invoice_number)"), $fullNumber);
                    })
                    ->exists();

                if ($alreadyExists) {
                    throw new \Exception("The invoice number '{$fullNumber}' is already in use and active in the database.");
                }
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
        $fy = substr($startYear, -2) .'-'. substr($endYear, -2);

        $normalizedLabel = strtolower($label);
        $query = self::withTrashed()->where('plant_id', $plantId);

        if ($normalizedLabel === 'purchase' || $normalizedLabel === 'bill') {
             $query->where(function($q) {
                 $q->whereRaw('LOWER(invoice_type) = ?', ['bill'])
                   ->orWhereRaw('LOWER(invoice_label) = ?', ['purchase']);
             });
             $defaultPrefix = "Bill/{$fy}/";
        } elseif ($normalizedLabel === 'batching' || $normalizedLabel === 'dispatch') {
             $query->whereRaw('LOWER(invoice_label) = ?', ['dispatch']);
             $defaultPrefix = "Inv/{$fy}/";
        } else {
             $query->where(function($q) {
                 $q->whereRaw('LOWER(invoice_type) = ?', ['sales'])
                   ->orWhereRaw('LOWER(invoice_label) = ?', ['tax invoice'])
                   ->orWhereNull('invoice_label');
             });
             $defaultPrefix = "INV/{$fy}/";
        }

        $prefix = $defaultPrefix;
        $hasCustomPrefix = false;
        if ($accountId) {
             $ledger = \App\Models\Ledger::withoutGlobalScopes()->find($accountId);
             if ($ledger && !empty(trim((string)$ledger->description))) {
                 $desc = trim((string)$ledger->description);
                 $prefix = str_ireplace('{fy}', $fy, $desc);
                 $hasCustomPrefix = true;
             }
        }
            
        // If the ledger specifies its own custom prefix in description, scope sequence to this account.
        // If it uses the plant default prefix, sequence must track across the plant for this prefix to avoid duplicates.
        if ($hasCustomPrefix) {
            $query->where('account_id', $accountId);
        }

        $lastInvoice = $query->where('prefix', $prefix)
            ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
            ->whereNull('deleted_at')
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
        $roundOff       = 0.0;

        $this->updateQuietly([
            'subtotal'       => $subtotal,
            'discount_total' => $discountTotal,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $rawTotal,
            'round_off'      => $roundOff,
            'balance_amount' =>  $rawTotal - (float)($this->paid_amount ?? 0),
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

    public function einvoiceRelation()
    {
        return $this->hasOne(EinvoiceInvoiceRel::class, 'invoice_id');
    }

    public function ewaybillDetail()
    {
        return $this->hasOne(EwaybillDetail::class, 'origin_id')->where('generation_type', 'invoice');
    }

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
                if (empty($itemData['item_id']) && !empty($itemData['mix_design_id'])) {
                    $itemData['item_id'] = $itemData['mix_design_id'];
                }

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
                if (empty($itemData['item_id']) && !empty($itemData['mix_design_id'])) {
                    $itemData['item_id'] = $itemData['mix_design_id'];
                }

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
                    
                    if ($discountType === '%') {
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
                    $qty = (float) (data_get($item, 'quantity') ?? data_get($item, 'product_quantity') ?? 0);
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

            $subtotal = $type === 'bill' ? ($subtotalSum - $source->discount_amount) : (float)($source->amount_untaxed ?? $subtotalSum);
            $discountTotal = $type === 'bill' ? ($discountSum + $source->discount_amount) : (float)($source->discount_amount ?? 0);
            $taxAmount = $type === 'bill' ? $taxSum : (float)($source->amount_tax ?? $taxSum);
            
            $adjustment = (float)($source->adjustment ?? 0);
            $shippingCharges = (float)($source->shipping_charges ?? 0);
            
            if ($type === 'bill') {
                $totalAmount = round($subtotal + $taxAmount + $shippingCharges + $adjustment, 2);
                $roundOff = 0.0;
            } else {
                $roundOff = (float) ($source->rounding_value ?? $source->round_off ?? 0);
                $pumpCharges = (float) ($source->pump_charges ?? 0);
                $passAmount = (float) ($source->pass_amount ?? 0);
                
                $sourceTotal = (float)($source->amount_total ?? $source->total_amount ?? 0);
                $calcBeforeRound = ($subtotal + $taxAmount + $pumpCharges + $shippingCharges + $passAmount + $adjustment) - $discountTotal;
                
                if ($sourceTotal > 0) {
                    $totalAmount = round($sourceTotal, 2);
                    if ($roundOff == 0) {
                        $roundOff = round($totalAmount - $calcBeforeRound, 2);
                    }
                } else {
                    $totalAmount = round(($calcBeforeRound + $roundOff), 2);
                }
            }

            // 1. Create the Invoice Header
            $invoiceHeaderData = [
                'plant_id'         => $plantId,
                'partner_id'       => $params['partner_id'] ?? ($type === 'bill' ? $source->vendor_id : $source->customer_id),
                'account_id'       => $params['account_id'] ?? null,
                'invoice_type'     => $type,
                'invoice_label'    => $params['invoice_label'] ?? null,
                'ref_id'           => $source->id,
                'ref_title'        => null,
                'invoice_date'     => $params['invoice_date'] ?? now(),
                'due_date'         => $params['due_date'] ?? $source->due_date,
                'subtotal'         => round($subtotal, 2),
                'global_discount'  => round($discountTotal, 2),
                'tax_amount'       => round($taxAmount, 2),
                'adjustment'       => round($adjustment, 2),
                'shipping_charges' => round($shippingCharges, 2),
                'round_off'        => round($roundOff, 2),
                'total_amount'     => round($totalAmount, 2),
                'balance_amount'   => round($totalAmount, 2),
                'status'           => self::STATUS_APPROVED,
                'notes'            => $params['notes'] ?? null,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ];

            // Resolve prefix automatically if not supplied
            $prefixDetails = self::generateNumber($plantId, $params['invoice_label'] ?? $type, $params['account_id'] ?? null);
            $autoPrefix = $params['prefix'] ?? $prefixDetails['prefix'];

            if (!empty($params['invoice_number'])) {
                $rawNumber = trim((string)$params['invoice_number']);
                if (!empty($autoPrefix) && str_starts_with($rawNumber, $autoPrefix)) {
                    $rawNumber = substr($rawNumber, strlen($autoPrefix));
                }
                $invoiceHeaderData['invoice_number'] = $rawNumber;
                $invoiceHeaderData['prefix'] = $autoPrefix;
            } else {
                $invoiceHeaderData['invoice_number'] = $prefixDetails['next_number'];
                $invoiceHeaderData['prefix'] = $autoPrefix;
            }

            $invoice = self::create($invoiceHeaderData);
 
            // 2. Create Invoice Items
            foreach ($itemsData as $itemData) {
                $invoice->items()->create($itemData);
            }

            // Refresh the invoice model to load the recalculated totals from DB
            $invoice->refresh();

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

    // ================================================================== Postable implementation
    // These methods fulfill the App\Contracts\Postable contract so that
    // AccountingPostingService can work with Invoice without knowing its internals.

    public function getDocumentId(): int
    {
        return $this->id;
    }

    public function getDocumentType(): string
    {
        // Normalize 'bill' to 'bill', 'sales' to 'invoice' — keeps DocumentTypeConfig simple
        $type = strtolower($this->invoice_type ?? 'invoice');
        return match($type) {
            'sales'    => 'invoice',
            'purchase' => 'bill',
            default    => $type, // 'invoice', 'bill', 'expense', 'stockin', 'stockout', etc.
        };
    }

    public function getVoucherNumber(): string
    {
        return $this->full_number ?? $this->invoice_number ?? '---';
    }

    public function getVoucherDate(): Carbon
    {
        return $this->invoice_date instanceof Carbon
            ? $this->invoice_date
            : Carbon::parse($this->invoice_date ?? now());
    }

    public function getPlantId(): int
    {
        // Never falls back to session — plant_id MUST be persisted on the record
        if (!$this->plant_id) {
            throw new \App\Exceptions\AccountingException(
                "Invoice #{$this->id} has no plant_id set. Cannot post to accounting."
            );
        }
        return (int) $this->plant_id;
    }

    public function getEntityId(): int
    {
        return (int) ($this->plant?->entity_id ?? 1);
    }

    public function getPartnerId(): ?int
    {
        return $this->partner_id ? (int) $this->partner_id : null;
    }

    public function getPartnerLedgerId(): ?int
    {
        return $this->partner?->ledger_id ? (int) $this->partner->ledger_id : null;
    }

    public function getPartnerName(): string
    {
        return $this->partner?->legal_name ?? 'Unknown Partner';
    }

    public function getBaseAccountId(): ?int
    {
        return $this->account_id ? (int) $this->account_id : null;
    }

    public function getContraAccountId(): ?int
    {
        return null; // Not applicable for invoices/bills
    }

    public function getSubtotalCents(): int
    {
        return (int) round((float)($this->subtotal ?? 0) * 100);
    }

    public function getTaxTotalCents(): int
    {
        return (int) round((float)($this->tax_amount ?? 0) * 100);
    }

    public function getTotalAmountCents(): int
    {
        return (int) round((float)($this->total_amount ?? 0) * 100);
    }

    /**
     * Build TaxLineDTO collection from the already-synced mm_order_taxes rows.
     * syncTaxSplits() MUST be called before postToAccounting() — which is already
     * guaranteed by the pipeline in createWithItems / updateWithItems / createFromSource.
     */
    public function getTaxLines(): Collection
    {
        $docType = $this->getDocumentType();
        $module  = match($docType) {
            'invoice' => 'Invoice',
            'bill'    => 'Purchase',
            default   => ucfirst($docType),
        };

        return OrderTax::query()
            ->where('order_id', $this->id)
            ->where('order_type', $module)
            ->get()
            ->map(fn($row) => new TaxLineDTO(
                amountCents: (int) round((float)($row->amount ?? 0) * 100),
                accountId:   $row->account_id ? (int) $row->account_id : null,
                taxName:     $row->name ?? 'Tax',
                taxId:       $row->tax_id ? (int) $row->tax_id : null,
            ));
    }

    /**
     * Build AdjustmentLineDTO collection for shipping, round-off, and global discount.
     * Adjustment lines are all handled here; account IDs are resolved via AccountDefaultSetting.
     */
    public function getAdjustmentLines(): Collection
    {
        $adjustments = collect();
        $plantId = $this->getPlantId();
        $module  = match($this->getDocumentType()) {
            'invoice' => 'Invoice',
            'bill'    => 'Purchase',
            default   => ucfirst($this->getDocumentType()),
        };

        $resolver = app(LedgerResolver::class);

        $map = [
            'shipping_charges' => ['key' => 'shipping_account',   'label' => 'Shipping',   'invert' => false],
            'adjustment'       => ['key' => 'adjustment_account',  'label' => 'Adjustment', 'invert' => false],
            'round_off'        => ['key' => 'round_off_account',   'label' => 'Round Off',  'invert' => false],
            'global_discount'  => ['key' => 'discount_account',    'label' => 'Discount',   'invert' => true],
        ];

        foreach ($map as $field => $cfg) {
            $value = (float)($this->{$field} ?? 0);
            if (abs($value) < 0.01) continue;

            $amountCents = (int) round($value * 100);
            if ($cfg['invert']) $amountCents = -$amountCents; // discount = negative

            $accountId = $resolver->resolve($plantId, $module, $cfg['key'], $cfg['label']);

            $adjustments->push(new AdjustmentLineDTO(
                amountCents: $amountCents,
                accountId:   $accountId,
                label:       $cfg['label'],
            ));
        }

        return $adjustments;
    }

    // ================================================================== Accounting trigger

    /**
     * Post (or re-post idempotently) this invoice to the journal.
     * Delegates entirely to AccountingPostingService via the Postable contract.
     * Kept as a public method for backward-compat with all call sites (controllers, observers).
     *
     * MUST be called inside an open DB transaction.
     *
     * @throws \App\Exceptions\AccountingException on user-fixable config errors
     */
    public function postToAccounting(): JournalEntry
    {
        // Make sure tax splits are always in sync before posting
        $this->syncTaxSplits($this->invoice_type ?? 'sales');

        return app(AccountingPostingService::class)->post($this);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function destroyer()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}