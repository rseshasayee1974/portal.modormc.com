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
        'empty_weight_truck' => 'decimal:3',
        'loaded_weight_truck' => 'decimal:3',
        'net_weight' => 'decimal:3',
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

    protected $appends = ['dispatch_date', 'is_tax_inclusive'];

    protected $hidden = [
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    public function getIsTaxInclusiveAttribute(): bool
    {
        return (bool) ($this->status?->is_tax_inclusive ?? false);
    }

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
    public function getAdjustmentAttribute() { return (float)($this->adjustment_amount ?? 0); }
    public function getShippingChargesAttribute() { return $this->transport_expenses; }
    public function getRoundingValueAttribute() { return $this->round_off; }
    public function getRefNoAttribute() { return $this->dispatch_no; }

    public function getItemsAttribute()
    {
        $gradeName = $this->mixDesign?->concrete_grade?->name 
            ?? $this->mixDesign?->concreteGrade?->name 
            ?? (!empty($this->mixDesign?->concrete_grade_id) ? \App\Models\ConcreteGrade::find($this->mixDesign->concrete_grade_id)?->name : null)
            ?? $this->salesOrder?->mixDesign?->concrete_grade?->name 
            ?? $this->salesOrder?->mixDesign?->concreteGrade?->name 
            ?? (!empty($this->salesOrder?->mixDesign?->concrete_grade_id) ? \App\Models\ConcreteGrade::find($this->salesOrder->mixDesign->concrete_grade_id)?->name : null)
            ?? $this->mixDesign?->grade 
            ?? $this->salesOrder?->mixDesign?->grade 
            ?? $this->mixDesign?->design_type 
            ?? $this->salesOrder?->mixDesign?->design_type 
           ;

        $gradeHsn = $this->mixDesign?->concreteGrade?->hsn_code
            ?? (!empty($this->mixDesign?->concrete_grade_id) ? \App\Models\ConcreteGrade::find($this->mixDesign->concrete_grade_id)?->hsn_code : null)
            ?? $this->salesOrder?->mixDesign?->concreteGrade?->hsn_code
            ?? (!empty($this->salesOrder?->mixDesign?->concrete_grade_id) ? \App\Models\ConcreteGrade::find($this->salesOrder->mixDesign->concrete_grade_id)?->hsn_code : null)
            ?? '38245010';

        return collect([(object)[
            'mix_design_id' => $this->mixdesign_id ?? $this->salesOrder?->mix_design_id,
            'product_id' => null, 
            'description' => "RMC Dispatch: " . $gradeName,
            'quantity' => (float) $this->delivered_qty,
            'uom_id' => $this->uom_id ?? $this->mixDesign?->unit_id ?? $this->salesOrder?->mixDesign?->unit_id,
            'unit_price' => (float) $this->load_rate,
            'discount_type' => '₹',
            'discount_amount' => 0,
            'total_discount' => 0,
            'price_subtotal' => (float) $this->load_untax_amount,
            'price_tax' => (float) $this->load_tax_amount,
            'price_total' => (float) ($this->load_untax_amount + $this->load_tax_amount),
            'tax_id' => $this->load_tax_id,
            'product' => (object)['title' => $gradeName, 'hsn_code' => $gradeHsn],
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

    /**
     * Cancel this dispatch and its linked batch, reverse sales order quantity,
     * cancel invoice/e-invoice, and generate credit note.
     */
    public function cancel(string $notes, ?int $userId = null): array
    {
        $userId = $userId ?? \Illuminate\Support\Facades\Auth::id() ?? 1;
        $now = now();

        // 1. Update Dispatch Status & Cancellation info
        $this->update([
            'dispatch_status' => 'Cancelled',
            'cancelled_at'    => $now,
            'cancelled_by'    => $userId,
            'cancelled_notes' => $notes,
        ]);

        // 2. Update linked Batch status to STATUS_CANCELLED (5)
        if ($this->batch) {
            $this->batch->update([
                'status' => \App\Models\Batch::STATUS_CANCELLED,
            ]);
        }

        // 3. Reverse Sales Order Quantity
        if ($this->salesOrder) {
            $this->salesOrder->refreshProduction();
        }

        // 4. Handle Invoiced Invoice, E-Invoice, and Credit Note
        $statusRecord = $this->status()->first();
        $invoice = $statusRecord?->invoice ?? ($this->invoice_id ? \App\Models\Invoice::find($this->invoice_id) : null);
        $creditNote = null;
        $eInvoiceCancelled = false;

        if ($invoice && strtolower((string)$invoice->status) !== \App\Models\Invoice::STATUS_CANCELLED) {
            // Attempt to cancel active E-Invoice IRN
            $irn = $invoice->einvoice_irn ?: $invoice->einv_irn;
            $einvStatus = $invoice->einvoice_status ?: $invoice->einv_status;
            if (!empty($irn) && ($einvStatus === 'ACT' || $einvStatus === 1)) {
                try {
                    $einvService = app(\App\Services\EInvoiceService::class);
                    $einvService->cancelIrn($invoice, '3', $notes);
                    $eInvoiceCancelled = true;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("E-Invoice IRN cancellation skipped/failed during dispatch cancellation: " . $e->getMessage());
                }
            }

            // Create Credit Note
            // $creditNote = $this->generateCreditNoteForInvoice($invoice, $notes, $userId);

            // Mark the original invoice as Cancelled
            $invoice->update([
                'status' => \App\Models\Invoice::STATUS_CANCELLED,
            ]);
        }

        return [
            'success'            => true,
            'dispatch_id'        => $this->id,
            'dispatch_status'    => 'Cancelled',
            'credit_note'        => $creditNote,
            'einvoice_cancelled' => $eInvoiceCancelled,
        ];
    }

    /**
     * Generate and post a Credit Note for an invoice being cancelled.
     */
    protected function generateCreditNoteForInvoice(\App\Models\Invoice $invoice, string $notes, int $userId): \App\Models\Invoice
    {
        $plantId = $invoice->plant_id ?? $this->plant_id;
        $details = \App\Models\Invoice::generateNumber($plantId, 'credit_note', $invoice->account_id);

        $creditNote = \App\Models\Invoice::create([
            'plant_id'               => $plantId,
            'account_id'             => $invoice->account_id,
            'prefix'                 => $details['prefix'],
            'invoice_number'         => $details['next_number'],
            'invoice_type'           => 'credit_note',
            'invoice_label'          => 'Credit Note',
            'ref_id'                 => $invoice->id,
            'ref_title'              => $invoice->invoice_number,
            'invoice_date'           => now()->toDateString(),
            'due_date'               => now()->toDateString(),
            'partner_id'             => $invoice->partner_id,
            'subtotal'               => $invoice->subtotal,
            'discount_amount'        => $invoice->discount_amount,
            'tax_amount'             => $invoice->tax_amount,
            'total_amount'           => $invoice->total_amount,
            'cgst_amount'            => $invoice->cgst_amount,
            'sgst_amount'            => $invoice->sgst_amount,
            'igst_amount'            => $invoice->igst_amount,
            'round_off'              => $invoice->round_off,
            'shipping_charges'       => $invoice->shipping_charges,
            'adjustment'             => $invoice->adjustment,
            'status'                 => \App\Models\Invoice::STATUS_APPROVED,
            'remarks'                => $notes,
            'created_by'             => $userId,
        ]);

        // Replicate invoice items into Credit Note
        foreach ($invoice->items as $item) {
            $newItem = $item->replicate();
            $newItem->invoice_id = $creditNote->id;
            $newItem->save();

            // Replicate order taxes for item if any
            foreach ($item->orderTaxes as $orderTax) {
                $newTax = $orderTax->replicate();
                $newTax->order_id = $creditNote->id;
                $newTax->order_items_id = $newItem->id;
                $newTax->save();
            }
        }

        // Post Credit Note to accounting ledger
        try {
            $creditNote->postToAccounting();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Accounting post for Credit Note #{$creditNote->id} failed: " . $e->getMessage());
        }

        return $creditNote;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}