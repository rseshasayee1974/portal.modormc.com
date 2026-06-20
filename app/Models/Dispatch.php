<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Traits\AuditFields;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;

class Dispatch extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping , TracksModelChanges;

    protected $table = 'mm_dispatches';

    protected $guarded = [];

    protected $casts = [
        'dispatch_time' => 'datetime',
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
    ];

    /**
     * Accounting Compatibility Accessors (for Invoice generation)
     */
    public function getAmountUntaxedAttribute() { return $this->load_untax_amount; }
    public function getAmountTaxAttribute() { return $this->load_tax_amount; }
    public function getAmountTotalAttribute() { return $this->load_total_amount; }
    public function getAdjustmentAttribute() { return $this->adjustment_amount; }
    public function getShippingChargesAttribute() { return $this->transport_expenses; }
    public function getRoundingValueAttribute() { return $this->round_off; }
    public function getRefNoAttribute() { return $this->dispatch_no; }

    public function getItemsAttribute()
    {
        return collect([(object)[
            'mix_design_id' => $this->mixdesign_id,
            'product_id' => null, 
            'description' => "RMC Dispatch: " . ($this->mixDesign?->design_name ?? 'Concrete'),
            'quantity' => $this->delivered_qty,
            'uom_id' => $this->uom_id ?? $this->mixDesign?->unit_id,
            'unit_price' => $this->load_rate,
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'total_discount' => 0,
            'price_subtotal' => $this->load_untax_amount,
            'price_tax' => $this->load_tax_amount,
            'price_total' => $this->load_total_amount,
            'tax_id' => $this->load_tax_id,
            'product' => (object)['title' => $this->mixDesign?->design_name, 'hsn_code' => '3824'],
        ]]);
    }

    /**
     * Relationships
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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
    public function drivers(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'id','personnel_id');
    }

    public function salesExecutive(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'sales_executive_id');
    }

    public function status(): HasOne
    {
        return $this->hasOne(DispatchStatus::class, 'dispatch_id');
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
        if ($this->status) {
            $this->status->update([
                'invoice_id'     => null,
                'invoice_number' => null,
                'invoice_date'   => null,
                'invoice_status' => 0,
            ]);
        }
    }
}