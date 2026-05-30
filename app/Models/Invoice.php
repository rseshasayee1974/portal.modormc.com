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
        'subtotal', 'discount_total', 'tax_amount', 'adjustment',
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
                $details = self::generateNumber($m->plant_id, $m->invoice_label ?? $m->invoice_type);
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
            \App\Models\DispatchStatus::where('invoice_id', $m->id)->update([
                'invoice_id'     => null,
                'invoice_number' => null,
                'invoice_date'   => null,
                'invoice_status' => 0,
            ]);
        });
    }

    // ------------------------------------------------------------------ business logic

    /**
     * Auto-generate invoice number: INV-YYYYMM-0001
     */
    public static function generateNumber(int $plantId, ?string $label = 'sales'): array
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
        } elseif ($normalizedLabel === 'batching') {
             $query->where('invoice_label', 'Dispatch');
             $prefix = "Inv/{$fy}/";
        } else {
             $query->where('invoice_type', 'sales')->whereNull('invoice_label');
             $prefix = "INV/{$fy}/";
        }
            
        $lastInvoice = (clone $query)->where('prefix', $prefix)
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

        $subtotal       = $items->sum('subtotal');
        $discountTotal  = $items->sum('discount_amount');
        $taxAmount      = $items->sum('line_tax_amount');
        
        // Add shipping charges to total
        $rawTotal       = $subtotal + $taxAmount + $this->adjustment + ($this->shipping_charges ?? 0);
        $rounded        = round($rawTotal);
        $roundOff       = $rounded - $rawTotal;

        $this->updateQuietly([
            'subtotal'       => $subtotal,
            'discount_total' => $discountTotal,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $rounded,
            'round_off'      => $roundOff,
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
     * Polymorphic tax splits at invoice level.
     */
    public function orderTaxes()
    {
        return $this->morphMany(OrderTax::class, 'order', 'order_type', 'order_id');
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
        return $this->belongsTo(Accounts::class, 'account_id');
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
            $invoice->syncTaxSplits();

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
            $this->syncTaxSplits();

            return $this;
        });
    }

    /**
     * Sync CGST/SGST/IGST splits at invoice level.
     */
    public function syncTaxSplits(): void
    {
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
                OrderTax::createIntraStateSplit($this, $item->subtotal, $fullRate, $item->tax_id,   $item->id);
            } else {
                OrderTax::createInterStateSplit($this, $item->subtotal, $fullRate, $item->tax_id,    $item->id);
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
