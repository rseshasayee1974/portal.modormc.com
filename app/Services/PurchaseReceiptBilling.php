<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptBilling
{
    /** Derive consumed original quantities from existing receipts and invoice quantity/UOM. */
    private function allocations(PurchaseOrder $order, PurchaseOrderItem $item): array
    {
        $order->loadMissing('billingHistory.items');
        $used = [];
        $allocations = [];
        $open = [];
        foreach ($order->billingHistory->sortBy('id') as $bill) {
            foreach ($open as $id => $previous) {
                if ($previous->deleted_at && $bill->created_at && $previous->deleted_at <= $bill->created_at) {
                    foreach ($allocations[$id] ?? [] as $receiptId => $qty) $used[$receiptId] -= $qty;
                    unset($open[$id]);
                }
            }
            foreach ($bill->items as $line) {
                if ((int) $line->purchase_order_item_id !== (int) $item->id) continue;
                $remaining = (float) $line->quantity;
                foreach ($item->history->sortBy('id') as $receipt) {
                    if ($receipt->created_at && $bill->created_at && $receipt->created_at > $bill->created_at) continue;
                    $available = max(0, (float) $receipt->received_qty - ($used[$receipt->id] ?? 0));
                    $ratio = (int) $line->uom_id === (int) $item->product_uom ? 1.0
                        : ((int) $line->uom_id === (int) $receipt->conversion_uom_id && (float) $receipt->received_qty > 0
                            ? (float) $receipt->conversion_quantity / (float) $receipt->received_qty : 0);
                    if ($ratio <= 0 || $available <= 0 || $remaining <= 0) continue;
                    $take = abs($remaining - $available * $ratio) < 0.000051
                        ? $available : min($available, $remaining / $ratio);
                    $used[$receipt->id] = ($used[$receipt->id] ?? 0) + $take;
                    $allocations[$bill->id][$receipt->id] = ($allocations[$bill->id][$receipt->id] ?? 0) + $take;
                    $remaining = max(0, $remaining - $take * $ratio);
                }
            }
            $open[$bill->id] = $bill;
        }
        $active = [];
        foreach ($order->billingHistory as $bill) {
            if ($bill->trashed()) continue;
            foreach ($allocations[$bill->id] ?? [] as $id => $qty) $active[$id] = ($active[$id] ?? 0) + $qty;
        }
        return [$active, $allocations];
    }

    public function originalQuantity(PurchaseOrder $order, PurchaseOrderItem $item, $bill, $line): float
    {
        if ((int) $line->uom_id === (int) $item->product_uom) return (float) $line->quantity;
        [, $allocations] = $this->allocations($order, $item);
        $quantity = round(array_sum($allocations[$bill->id] ?? []), 2);
        if ($quantity <= 0 && (float) $line->quantity > 0) {
            throw ValidationException::withMessages(['items' => 'The original billed quantity could not be resolved from inward history.']);
        }
        return $quantity;
    }

    public function quantity(PurchaseOrder $order, PurchaseOrderItem $item, bool $converted): array
    {
        $base = max(0, (float) $item->received_quantity - (float) $item->invoiced_quantity);
        [$used] = $this->allocations($order, $item);
        $tracked = array_sum($used);

        // Bills created before receipt tracking consumed the oldest receipts first.
        $legacy = max(0, (float) $item->invoiced_quantity - $tracked);
        $remaining = $base;
        $quantity = 0;
        $uom = null;
        foreach ($item->history->sortBy('id') as $receipt) {
            $available = max(0, (float) $receipt->received_qty - ($used[$receipt->id] ?? 0));
            $skip = min($legacy, $available);
            $legacy -= $skip;
            $available -= $skip;
            $take = min($remaining, $available);
            if ($take <= 0) continue;
            $remaining -= $take;
            if ($converted) {
                if ((float) $receipt->conversion_quantity <= 0 || !$receipt->conversion_uom_id) {
                    throw ValidationException::withMessages(['items' => 'Enter conversion quantity and converted UOM on each unbilled inward receipt.']);
                }
                if ((int) $receipt->conversion_uom_id === (int) $item->product_uom && abs((float) $receipt->conversion_quantity - (float) $receipt->received_qty) > 0.00001) {
                    throw ValidationException::withMessages(['items' => 'Choose a different converted UOM when the conversion changes the quantity.']);
                }
                if ($uom !== null && $uom !== (int) $receipt->conversion_uom_id) {
                    throw ValidationException::withMessages(['items' => 'Unbilled receipts for the same PO line must use the same converted UOM.']);
                }
                $uom = (int) $receipt->conversion_uom_id;
                $quantity += (float) $receipt->conversion_quantity * $take / (float) $receipt->received_qty;
            }
        }
        if ($converted && $remaining > 0.00001) {
            throw ValidationException::withMessages(['items' => 'Conversion billing requires inward receipts for all unbilled received quantities.']);
        }
        if ($converted && $base > 0 && round($quantity, 4) <= 0) {
            throw ValidationException::withMessages(['items' => 'The converted bill quantity must be at least 0.0001.']);
        }

        return [
            'quantity' => $converted ? round($quantity, 4) : $base,
            'uom_id' => $converted ? $uom : $item->product_uom,
            'received_quantity' => $base,
        ];
    }
}
