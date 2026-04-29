<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AuditFields;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_invoices';

    protected $fillable = [
        'plant_id', 'partner_id', 'account_id', 'journal_id',
        'invoice_type', 'invoice_label', 'ref_id', 'ref_title', 'truck_id',
        'invoice_number', 'prefix', 'invoice_date', 'due_date', 'period',
        'subtotal', 'discount_total', 'tax_amount', 'adjustment',
        'shipping_charges', 'shipping_tax_id',
        'total_amount', 'round_off',
        'status', 'einvoice_status', 'is_duplicate', 'is_sent', 'is_reconciled',
        'is_active',
        'created_by', 'updated_by',
    ];

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
        $last = self::where('plant_id', $plantId)->orderBy('id', 'desc')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'INV-' . date('Ym') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
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

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

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

            $taxRate = 0;
            if (!empty($data['tax_id'])) {
                $tax = Tax::find($data['tax_id']);
                $taxRate = $tax?->rate ?? 0;
            }

            $invoice = self::create($data);

            foreach ($itemsData as $itemData) {
                $item = new InvoiceItem($itemData);
                $item->invoice_id = $invoice->id;
                $item->compute($taxRate);
                $item->save();
            }

            $invoice->refresh();
            $invoice->recalculate();
            $invoice->syncTaxSplits($taxRate);

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

            $taxRate = 0;
            if (!empty($data['tax_id'])) {
                $tax = Tax::find($data['tax_id']);
                $taxRate = $tax?->rate ?? 0;
            }

            $this->update($data);

            $keptIds = collect($itemsData)->pluck('id')->filter()->toArray();
            $this->items()->whereNotIn('id', $keptIds)->delete();

            foreach ($itemsData as $itemData) {
                if (!empty($itemData['id'])) {
                    $item = InvoiceItem::find($itemData['id']);
                    $item->fill($itemData);
                } else {
                    $item = new InvoiceItem($itemData);
                    $item->invoice_id = $this->id;
                }
                $item->compute($taxRate);
                $item->save();
            }

            $this->refresh();
            $this->recalculate();
            $this->syncTaxSplits($taxRate);

            return $this;
        });
    }

    /**
     * Sync CGST/SGST/IGST splits at invoice level.
     */
    public function syncTaxSplits(float $taxRate): void
    {
        $this->orderTaxes()->delete();

        if ($taxRate > 0) {
            $taxableAmount = $this->subtotal;
            
            // Basic logic: use state from partner's address vs plant's address
            $plantState   = $this->plant?->addresses()?->first()?->state?->state_code;
            $partnerState = $this->partner?->addresses()?->first()?->state?->state_code;

            if ($plantState && $partnerState && $plantState === $partnerState) {
                OrderTax::createIntraStateSplit($this, $taxableAmount, $taxRate);
            } else {
                OrderTax::createInterStateSplit($this, $taxableAmount, $taxRate);
            }
        }
    }
}
