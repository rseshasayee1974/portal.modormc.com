<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

    protected $table = 'mm_purchase_orders';

    protected $fillable = [
        'plant_id',
        'vendor_id',
        'vehicle_id',
        'po_number',
        'ref_no',
        'bill_number',
        'billed_date',
        'date_order',
        'date_approve',
        'date_planned',
        'delivery_date',
        'due_date',
        'partner_reference',
        'state',
        'approve_status',
        'invoice_status',
        'receipt_status',
        'journal_status',
        'closed_status',
        'currency_id',
        'exchange_rate',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
        'discount_amount',
        'shipping_charges',
        'adjustment',
        'rounding_value',
        'common_tax_id',
        'shipping_tax_id',
        'origin',
        'notes',
        'terms_conditions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_order' => 'date',
        'date_approve' => 'date',
        'date_planned' => 'date',
        'delivery_date' => 'date',
        'due_date' => 'date',
        'billed_date' => 'date',
        'amount_untaxed' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'amount_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'rounding_value' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];


    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Patron::class, 'vendor_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Machine::class, 'vehicle_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'order_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Recalculate totals based on items.
     */
    public function recalculateTotals()
    {
        // Query fresh aggregates to avoid stale loaded relation values.
        $itemSubtotalsSum = (float) $this->items()->sum('price_subtotal');
        $globalDiscount = (float) ($this->discount_amount ?? 0);

        $amountUntaxed = $itemSubtotalsSum - $globalDiscount;
        $amountTax = (float) $this->items()->sum('price_tax');
        $shippingCharges = (float) ($this->shipping_charges ?? 0);
        $adjustment = (float) ($this->adjustment ?? 0);

        $this->amount_untaxed = $amountUntaxed;
        $this->amount_tax = $amountTax;
        $this->amount_total = $amountUntaxed + $amountTax + $shippingCharges + $adjustment;

        $this->save();
    }

    public static function getFinancialYearString($date = null): string
    {
        $timestamp = $date ? strtotime($date) : time();
        $currentMonth = (int) date('m', $timestamp);
        $currentYear = (int) date('Y', $timestamp);

        if ($currentMonth < 4) {
            $y1 = $currentYear - 1;
            $y2 = $currentYear;
        } else {
            $y1 = $currentYear;
            $y2 = $currentYear + 1;
        }

        return substr($y1, -2) . substr($y2, -2); // eg "2526" for 2025-2026
    }

    public static function generateNextRefId($plantId, $date = null)
    {
        $finYearString = self::getFinancialYearString($date);
        $prefix = "PO-" . $finYearString . "-";

        $lastOrder = self::where('plant_id', $plantId)->whereNull('deleted_at')
            ->where('po_number', 'like', $prefix . '%')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder && preg_match('/' . preg_quote($prefix, '/') . '(\d+)/i', $lastOrder->po_number, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

    // Return with 4 digit zero-padding, e.g., PO-2526-0001
        return ['prefix'=>$prefix,'ref_no' => sprintf('%s%04d', $prefix, $nextNumber),'financial_year' => $finYearString];
    }

    /**
     * Create PO with items.
     */
    public static function storeWithItems(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userId = Auth::id();
            $plantId = $data['plant_id'] ?? session('active_plant_id');

            if (!$userId) {
                throw ValidationException::withMessages([
                    'user' => 'Authenticated user is required.',
                ]);
            }

            if (!$plantId) {
                throw ValidationException::withMessages([
                    'plant_id' => 'Active plant is required to create a purchase order.',
                ]);
            }

            $items = $data['items'] ?? [];
            $headerData = Arr::except($data, ['items']);

            // Handle PO Number generation if not provided
            if (empty($headerData['po_number'])) {
                $refData = self::generateNextRefId($plantId, $headerData['date_order'] ?? null);
                $headerData['po_number'] = $refData['ref_no'];
                
                // Parse the sequence for the pure ref_no column, e.g. "0003"
                preg_match('/(\d+)$/', $refData['ref_no'], $m);
                $headerData['ref_no'] = isset($m[1]) ? str_pad($m[1], 4, '0', STR_PAD_LEFT) : $refData['ref_no'];
            }

            $purchaseOrder = self::create(array_merge($headerData, [
                'plant_id' => $plantId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'state' => $headerData['state'] ?? 'draft',
            ]));

            foreach ($items as $itemData) {
                $item = $purchaseOrder->items()->create(array_merge($itemData, [
                    'plant_id' => $plantId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]));
                $item->calculateItemTotals();
            }

            $purchaseOrder->recalculateTotals();
            $purchaseOrder->refreshReceiptStatus();

            return $purchaseOrder;
        });
    }

    public function updateWithItems(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userId = Auth::id();
            $plantId = $this->plant_id ?: ($data['plant_id'] ?? session('active_plant_id'));

            if (!$userId) {
                throw ValidationException::withMessages([
                    'user' => 'Authenticated user is required.',
                ]);
            }

            if (!$plantId) {
                throw ValidationException::withMessages([
                    'plant_id' => 'Plant is required to update a purchase order.',
                ]);
            }

            $headerData = Arr::except($data, ['items']);
            $headerData['updated_by'] = $userId;

            if (!$this->plant_id) {
                $headerData['plant_id'] = $plantId;
            }

            $this->update($headerData);

            if (array_key_exists('items', $data)) {
                $this->syncItems($data['items'] ?? [], (int) $userId, (int) $plantId);
            }

            $this->unsetRelation('items');
            $this->recalculateTotals();
            $this->refreshReceiptStatus();

            return $this;
        });
    }

    protected function syncItems(array $itemsPayload, int $userId, int $plantId): void
    {
        $incomingItemIds = collect($itemsPayload)->pluck('id')->filter()->values()->all();

        $deleteQuery = $this->items();
        if (!empty($incomingItemIds)) {
            $deleteQuery->whereNotIn('id', $incomingItemIds);
        }
        $deleteQuery->delete();

        foreach ($itemsPayload as $itemData) {
            $payload = Arr::except($itemData, ['id']);

            if (!empty($itemData['id'])) {
                $item = $this->items()->find($itemData['id']);
                if (!$item) {
                    throw ValidationException::withMessages([
                        'items' => "Invalid item id {$itemData['id']} for this purchase order.",
                    ]);
                }

                $item->update(array_merge($payload, ['updated_by' => $userId]));
            } else {
                $item = $this->items()->create(array_merge($payload, [
                    'plant_id' => $plantId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]));
            }

            $item->calculateItemTotals();
        }
    }

    protected function receiveItemQuantity(
        PurchaseOrderItem $item,
        float $receivedNow,
        ?string $receivedDate,
        ?string $inwardNo,
        int $userId
    ): void {
        if ($receivedNow <= 0) {
            return;
        }

        $remaining = max(0, (float) $item->product_quantity - (float) $item->received_quantity);
        $acceptedQty = min($receivedNow, $remaining);

        if ($acceptedQty <= 0) {
            return;
        }

        $entryDate = $receivedDate ?: date('Y-m-d');
        $newReceivedQty = (float) $item->received_quantity + $acceptedQty;

        PurchaseOrderHistory::create([
            'plant_id' => $this->plant_id,
            'order_id' => $this->id,
            'order_item_id' => $item->id,
            'received_date' => $entryDate,
            'product_id' => $item->product_id,
            'uom_id' => $item->product_uom,
            'used_quantity' => $newReceivedQty,
            'received_qty' => $acceptedQty,
            'unit_price' => $item->unit_price,
            'inward_no' => $inwardNo ?: PurchaseOrderHistory::generateNextInwardNo($this->plant_id, $entryDate),
            'status' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $item->received_quantity = $newReceivedQty;
        $item->updated_by = $userId;
        $item->save();

        Quantity::create([
            'plant_id' => $this->plant_id,
            'product_id' => $item->product_id,
            'uom_id' => $item->product_uom,
            'quantity' => $acceptedQty,
            'date' => $entryDate,
            'is_warehouse' => true,
            'status' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    protected function refreshReceiptStatus(): void
    {
        $totals = $this->items()
            ->selectRaw('COALESCE(SUM(product_quantity), 0) as ordered_qty, COALESCE(SUM(received_quantity), 0) as received_qty')
            ->first();

        $orderedQty = (float) ($totals->ordered_qty ?? 0);
        $receivedQty = (float) ($totals->received_qty ?? 0);

        $status = 0; // none
        if ($receivedQty > 0 && $orderedQty > 0) {
            $status = $receivedQty >= $orderedQty ? 2 : 1; // 1: partial, 2: full
        }

        if ((int) $this->receipt_status !== $status) {
            $this->receipt_status = $status;
            $this->save();
        }
    }
}
