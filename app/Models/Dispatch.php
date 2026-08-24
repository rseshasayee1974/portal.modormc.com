<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;

class Dispatch extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_dispatches';

    protected $guarded = [];

    protected $casts = [
        'dispatch_time' => 'datetime',
        'empty_time' => 'datetime',
        'load_time' => 'datetime',
        'delivered_qty' => 'decimal:3',
        'load_rate' => 'decimal:2',
        'load_tax_amount' => 'decimal:2',
        'load_untax_amount' => 'decimal:2',
        'load_total_amount' => 'decimal:2',
        'pass_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'transport_expenses' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'pump_charges' => 'decimal:2',
    ];

    protected $appends = ['dispatch_date'];

    public function getDispatchDateAttribute()
    {
        return $this->dispatch_time ? $this->dispatch_time->toIso8601String() : ($this->created_at ? $this->created_at->toIso8601String() : null);
    }

    public function getDeliveredQtyAttribute($value)
    {
        $val = (float) $value;
        if ($val > 0) {
            return $val;
        }
        return (float) ($this->batch?->batch_size ?? 0);
    }

    /**
     * Accounting Compatibility Accessors (for Invoice generation)
     */
    public function getAmountUntaxedAttribute() { return $this->load_untax_amount; }
    public function getAmountTaxAttribute() { return $this->load_tax_amount; }
    public function getAmountTotalAttribute() { return $this->load_total_amount; }
    public function getAdjustmentAttribute() { return (float)($this->adjustment_amount ?? 0) + (float)($this->pass_amount ?? 0); }
    public function getShippingChargesAttribute() { return $this->transport_expenses; }
    public function getRoundingValueAttribute() { return $this->round_off; }
    public function getRefNoAttribute() { return $this->dispatch_no; }

    public function getItemsAttribute()
    {
        $designName = $this->mixDesign?->design_name;
        if (empty($designName) && $this->salesOrder) {
            $designName = $this->salesOrder->mixDesign?->design_name;
        }
        $designName = $designName ?? 'Concrete';

        return collect([(object)[
            'mix_design_id' => $this->mixdesign_id ?? $this->salesOrder?->mix_design_id,
            'product_id' => null, 
            'description' => "RMC Dispatch: " . $designName,
            'quantity' => $this->delivered_qty,
            'uom_id' => $this->uom_id ?? $this->mixDesign?->unit_id ?? $this->salesOrder?->mixDesign?->unit_id,
            'unit_price' => $this->load_rate,
            'discount_type' => '₹',
            'discount_amount' => 0,
            'total_discount' => 0,
            'price_subtotal' => $this->load_untax_amount,
            'price_tax' => $this->load_tax_amount,
            'price_total' => $this->load_total_amount,
            'tax_id' => $this->load_tax_id,
            'product' => (object)['title' => $designName, 'hsn_code' => '3824'],
        ]]);
    }

    /**
     * Relationships
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customerPO(): BelongsTo
    {
        return $this->belongsTo(CustomerPO::class, 'customer_po_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Patron::class, 'transport_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function mixDesign(): BelongsTo
    {
        return $this->belongsTo(MixDesign::class, 'mixdesign_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    public function loadSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'load_site_id');
    }

    public function unloadSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'unload_site_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
    }
    // removed old drivers relation

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'operator_id');
    }

    public function salesExecutive(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id');
    }

    public function concretePump(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'concrete_pump');
    }

    public function status(): HasOne
    {
        return $this->hasOne(DispatchStatus::class, 'dispatch_id');
    }

    public function loadTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'load_tax_id');
    }

    public function payments()
    {
        return $this->hasMany(DispatchPayment::class, 'dispatch_id');
    }

    /**
     * Generates a WhatsApp Click-to-Chat URL for this dispatch.
     */
    public function getWhatsAppUrl(): ?string
    {
        $this->load(['customer.contacts', 'workOrder', 'mixDesign', 'truck', 'driver']);
        
        $customer = $this->customer;
        if (!$customer) return null;
        
        $contact = $customer->contacts()->where('is_primary', 1)->first() ?? $customer->contacts()->first();
        if (!$contact || !$contact->mobile) return null;

        $mobile = preg_replace('/[^0-9]/', '', $contact->mobile);
        // Add country code if 10 digits
        if (strlen($mobile) === 10) {
            $mobile = '91' . $mobile;
        }

        $notification = new \App\Notifications\DispatchCompletedNotification($this);
        $message = $notification->toWhatsAppMessage();

        return "https://wa.me/" . $mobile . "?text=" . urlencode($message);
    }

    /**
     * Link an invoice to this dispatch and mark it as Invoiced
     */
    public function invoice(\App\Models\Invoice $invoice)
    {
        $this->update(['dispatch_status' => 'Invoiced']);
        $this->status()->updateOrCreate(
            ['dispatch_id' => $this->id],
            [
                'plant_id'       => $this->plant_id,
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date'   => $invoice->invoice_date,
                'invoice_status' => 1,
            ]
        );
    }

    /**
     * Unlink invoice from this dispatch and reset it to Draft
     */
    public function resetInvoice()
    {
        $this->update(['dispatch_status' => 'Draft']);
        if ($statusRecord = $this->status()->first()) {
            $statusRecord->update([
                'invoice_id'     => null,
                'invoice_number' => null,
                'invoice_date'   => null,
                'invoice_status' => 0,
            ]);
        }
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}