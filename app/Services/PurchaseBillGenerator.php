<?php
/*
Author: ragul-onemodo
Created: 2026-10-06 15:42:46 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseBillGenerator
{
    public function generate(Request $request, PurchaseOrder $purchase_order): ?Invoice
    {
        $request->validate([
            'account_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('mm_ledgers', 'id')->where('plant_id', $purchase_order->plant_id)->whereNull('deleted_at')],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'items' => ['sometimes', 'array'],
            'inward_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'inward_ids.*' => ['required', 'integer', 'distinct', \Illuminate\Validation\Rule::exists('mm_purchase_order_history', 'id')->where('order_id', $purchase_order->id)->whereNull('deleted_at')],
            'items.*.inward_id' => ['required_with:inward_ids', 'integer', 'distinct', \Illuminate\Validation\Rule::exists('mm_purchase_order_history', 'id')->where('order_id', $purchase_order->id)->whereNull('deleted_at')],
            'items.*.order_item_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('mm_purchase_order_items', 'id')->where('order_id', $purchase_order->id)->whereNull('deleted_at')],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);
        return DB::transaction(function () use ($request, $purchase_order) {
            $order = PurchaseOrder::whereKey($purchase_order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array(strtolower($order->state), ['approved', 'billed', 'received']), 422, 'Only approved purchase orders can be billed.');
            $order->load('items.product');
            $invoiceData = $this->buildData($request, $order);
            if (!$invoiceData) {
                return null;
            }

            $receivedQuantities = $invoiceData['_received_quantities'];
            unset($invoiceData['_received_quantities']);
            $invoice = Invoice::createWithItems($invoiceData);
            if (in_array($invoice->status, [Invoice::STATUS_APPROVED, Invoice::STATUS_PAID], true)) {
                $invoice->postToAccounting();
            }

            foreach ($order->items as $item) {
                if (isset($receivedQuantities[$item->id])) {
                    $item->update(['invoiced_quantity' => (float) $item->invoiced_quantity + $receivedQuantities[$item->id]]);
                }
            }
            $fullyBilled = $order->items->every(fn ($item) => (float) $item->invoiced_quantity >= (float) $item->received_quantity);
            $order->update([
                'invoice_status' => 1,
                'billing_id' => $invoice->id,
                'state' => (int) $order->receipt_status === 2 && $fullyBilled ? 'billed' : 'approved',
                'billed_date' => $request->input('invoice_date', now()),
                'journal_status' => 1,
            ]);

            return $invoice;
        });
    }

    public function buildData(Request $request, PurchaseOrder $purchase_order): array
    {
        // Create separate array for loading invoice details from purchase_order
        $itemsData = [];
        $subtotalSum = 0;
        $taxSum = 0;
        $discountSum = 0;
        $receiptOrderValue = 0;
        $billRates = collect($request->input('items', []))->keyBy('order_item_id');
        $purchase_order->loadMissing(['items.history', 'bills.items']);
        $quantities = new \App\Services\PurchaseReceiptBilling;
        $receivedQuantities = [];
        $billingLines = [];
        if ($request->has('inward_ids')) {
            $rates = collect($request->input('items', []))->keyBy('inward_id');
            foreach ($request->input('inward_ids', []) as $receiptId) {
                $item = $purchase_order->items->first(fn ($item) => $item->history->contains('id', $receiptId));
                if (!$item) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['inward_ids' => 'The selected inward does not belong to this purchase order.']);
                }
                $rate = $rates->get($receiptId);
                if ($rate && (int) $rate['order_item_id'] !== (int) $item->id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'The inward does not belong to the selected PO line.']);
                }
                $billingLines[] = [$item, (int) $receiptId, $rate['unit_price'] ?? $item->unit_price];
            }
        } else {
            foreach ($purchase_order->items as $item) {
                $billingLines[] = [$item, null, $billRates->get($item->id)['unit_price'] ?? $item->unit_price];
            }
        }

        foreach ($billingLines as [$item, $receiptId, $rate]) {
            if ($receiptId === null && (float)$item->received_quantity <= (float)$item->invoiced_quantity) {
                continue;
            }

            $receipt = $receiptId !== null ? $item->history->firstWhere('id', $receiptId) : null;
            $converted = $receipt && (float) $receipt->conversion_quantity > 0;
            $billingQuantity = $quantities->quantity($purchase_order, $item, $converted, $receiptId);
            if ($receipt && !$converted) {
                $billingQuantity['uom_id'] = $receipt->uom_id;
            }
            $baseQty = $billingQuantity['received_quantity'];
            if ($receiptId !== null && $baseQty <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['inward_ids' => 'A selected inward is already billed or has no received quantity. Refresh and select unbilled inwards.']);
            }
            $receivedQuantities[$item->id] = ($receivedQuantities[$item->id] ?? 0) + $baseQty;
            $qty = $billingQuantity['quantity'];
            $priceUnit = (float) $rate;
            // Allocate fixed PO charges by received share, independently of the bill's rate.
            $receiptOrderValue += (float)$item->product_quantity > 0
                ? (float)$item->price_subtotal * $baseQty / (float)$item->product_quantity : 0;

            // Recalculate discount
            $discountType = $item->discount_type;
            $discountVal = (float) $item->discount_amount;
            $lineSubtotalBeforeDiscount = $qty * $priceUnit;

            if ($discountType === '%') {
                $lineDiscount = ($lineSubtotalBeforeDiscount * $discountVal) / 100;
            } else {
                $orderedQty = (float) $item->product_quantity;
                if ($baseQty == $orderedQty || $orderedQty == 0) {
                    $lineDiscount = $discountVal;
                } else {
                    $lineDiscount = ($discountVal / $orderedQty) * $baseQty;
                }
            }

            $lineSubtotal = $lineSubtotalBeforeDiscount - $lineDiscount;
            if ($lineSubtotal < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'The bill rate must cover the purchase order line discount.',
                ]);
            }

            // Recalculate tax
            $taxRate = 0;
            $taxId = $item->tax_id;
            if ($taxId) {
                $tax = \App\Models\Tax::where('id', $taxId)
                    ->where('plant_id', session('active_plant_id', $purchase_order->plant_id))
                    ->whereNull('deleted_at')
                    ->first();
                if ($tax) {
                    $taxRate = (float) $tax->tax_rate;
                }
            }

            if ($purchase_order->tax_inclusive && $taxRate > 0) {
                $net = $lineSubtotal;
                $lineSubtotal = round($net / (1 + $taxRate / 100), 2);
                $lineTax = round($net - $lineSubtotal, 2);
            } else {
                $lineTax = ($lineSubtotal * $taxRate) / 100;
            }
            $lineTotal = $lineSubtotal + $lineTax;

            $subtotalSum += $lineSubtotal;
            $taxSum += $lineTax;
            $discountSum += $lineDiscount;

            $itemsData[] = [
                'purchase_order_item_id' => $item->id,
                'purchase_order_history_id' => $receiptId,
                'item_id'   => $item->product_id,
                'item_name'       => $item->product->title ?? $item->description,
                'hsn_code'        => $item->product->hsn_code ?? null,
                'quantity'        => $qty,
                'uom_id'          => $billingQuantity['uom_id'],
                'price_unit'      => $priceUnit,
                'discount_type'   => $discountType,
                'discount'        => $discountType === '%' ? $discountVal : $lineDiscount,
                'discount_amount' => $lineDiscount,
                'subtotal'        => $lineSubtotal,
                'line_tax_amount' => $lineTax,
                'line_total'      => $lineTotal,
                'tax_id'          => $taxId,
            ];
        }

        if (empty($itemsData)) {
            return [];
        }

        // Allocate order-level charges by value so each receipt bears only its share.
        $orderedValue = (float)$purchase_order->items->sum(fn ($item) => (float)$item->price_subtotal);
        $share = $orderedValue > 0 ? min(1, $receiptOrderValue / $orderedValue) : 0;
        $globalDiscount = round((float)$purchase_order->discount_amount * $share, 2);
        $subtotal = $subtotalSum - $globalDiscount;
        $discountTotal = $discountSum + $globalDiscount;
        $taxAmount = $taxSum;

        $adjustment = round((float)$purchase_order->adjustment * $share, 2);
        $shippingCharges = round((float)$purchase_order->shipping_charges * $share, 2);

        $totalAmount = $subtotal + $taxAmount + $shippingCharges + $adjustment;
        $roundedTotal = round($totalAmount);
        $roundOff = $roundedTotal - $totalAmount;

        return [
            'plant_id'         => session('active_plant_id', $purchase_order->plant_id),
            'partner_id'       => $purchase_order->vendor_id,
            'account_id'       => $request->input('account_id'),
            'invoice_type'     => 'Bill',
            'invoice_label'    => 'purchase',
            'is_tax_inclusive' => (bool) $purchase_order->tax_inclusive,
            'ref_id'           => $purchase_order->id,
            'ref_title'        => $purchase_order->po_number ?? $purchase_order->ref_no,
            'invoice_date'     => $request->input('invoice_date', now()),
            'due_date'         => $request->input('due_date', $purchase_order->due_date),
            'subtotal'         => $subtotal,
            'global_discount_type' => '₹',
            'global_discount'  => $globalDiscount,
            'discount_total'   => $discountTotal,
            'tax_amount'       => $taxAmount,
            'adjustment'       => $adjustment,
            'shipping_charges' => $shippingCharges,
            'round_off'        => $roundOff,
            'total_amount'     => $roundedTotal,
            'balance_amount'   => $roundedTotal,
            'status'           => \App\Models\Invoice::STATUS_APPROVED,
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
            'items'            => $itemsData,
            '_received_quantities' => $receivedQuantities,
        ];

    }
}
