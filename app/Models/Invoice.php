<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\AuditFields;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_invoices';

    protected $fillable = [
        'plant_id', 'partner_id', 'account_id', 
        'invoice_type', 'invoice_label', 'ref_id', 'ref_title',  
        'invoice_number', 'prefix', 'invoice_date', 'due_date', 'period',
        'subtotal', 'discount_total', 'tax_amount', 'adjustment',
        'shipping_charges', 'shipping_tax_id',
        'total_amount', 'round_off',
        'status', 'einvoice_status', 'is_duplicate', 'is_sent', 'is_reconciled',
        'is_active',
        'created_by', 'updated_by',
    ];

    protected $appends = ['encrypted_id'];

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
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
            $m->invoice_number = $m->invoice_number ?? self::generateNumber($m->plant_id);
        });
    }

    // ------------------------------------------------------------------ business logic

    /**
     * Auto-generate invoice number: INV-YYYYMM-0001
     */
    public static function generateNumber(int $plantId): string
    {
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $endYear = $startYear + 1;
        $fy = substr((string)$startYear, 2) . substr((string)$endYear, 2);

        $fyStart = \Carbon\Carbon::create($startYear, 4, 1, 0, 0, 0);
        
        $count = self::where('plant_id', $plantId)
            ->where('created_at', '>=', $fyStart)
            ->count();
            
        $next = $count + 1;

        return "INV-{$fy}-" . str_pad((string)$next, 2, '0', STR_PAD_LEFT);
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
            $this->items()->whereNotIn('id', $keptIds)->delete();

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

        // 1. Group items by tax_id to handle multiple tax rates correctly
        $groupedItems = $this->items()->with('tax')->get()->groupBy('tax_id');

        foreach ($groupedItems as $taxId => $items) {
            $taxableAmount = $items->sum('subtotal');
            $taxAmount     = $items->sum('line_tax_amount');
            
            if ($taxAmount <= 0) continue;

            $tax = Tax::find($taxId);
            // Derive rate from items if tax record missing (fallback)
            $fullRate = $tax ? $tax->tax_rate : (($taxableAmount > 0) ? ($taxAmount / $taxableAmount) * 100 : 0);

            if ($fullRate <= 0) continue;

            $plantAddr   = $this->plant?->addresses()?->first();
            $partnerAddr = $this->partner?->addresses()?->first();
            
            $plantState   = $plantAddr?->state?->state_code ?? $plantAddr?->state_code;
            $partnerState = $partnerAddr?->state?->state_code ?? $partnerAddr?->state_code;

            if ($plantState && $partnerState && $plantState === $partnerState) {
                OrderTax::createIntraStateSplit($this, $taxableAmount, $fullRate, $taxId);
            } else {
                OrderTax::createInterStateSplit($this, $taxableAmount, $fullRate, $taxId);
            }
        }

        // 2. Handle shipping tax split if applicable
        if ($this->shipping_charges > 0 && $this->shipping_tax_id) {
            $shippingTax = Tax::find($this->shipping_tax_id);
            if ($shippingTax && $shippingTax->tax_rate > 0) {
                $plantState   = $this->plant?->addresses()?->first()?->state?->state_code;
                $partnerState = $this->partner?->addresses()?->first()?->state?->state_code;

                if ($plantState && $partnerState && $plantState === $partnerState) {
                    OrderTax::createIntraStateSplit($this, $this->shipping_charges, $shippingTax->tax_rate, $this->shipping_tax_id);
                } else {
                    OrderTax::createInterStateSplit($this, $this->shipping_charges, $shippingTax->tax_rate, $this->shipping_tax_id);
                }
            }
        }
    }

    public function resolveRouteBinding($value, $field = null)
    {
        try {
            $decrypted = decrypt($value);
            return $this->where($field ?? $this->getRouteKeyName(), $decrypted)->first();
        } catch (\Exception $e) {
            return parent::resolveRouteBinding($value, $field);
        }
    }
}
