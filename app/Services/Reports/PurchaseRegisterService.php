<?php

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Builder;

class PurchaseRegisterService extends RegisterReportService
{
    protected function reportType(): string { return 'purchase_register'; }
    protected function query(array $filters): Builder { return $this->repository->getPurchaseRegisterQuery($filters); }
    protected function mapRow($item): array { return $this->mapPurchaseRow($item); }

    public function buildReportFromQuery(Builder $query, array $filters = [], bool $allRows = true): array
    {
        // Legacy exports may supply their own query instead of using the repository.
        return parent::buildReportFromQuery((clone $query)->forPurchaseRegister(), $filters, $allRows);
    }

    public function mapPurchaseRow($item): array
    {
        $order = $item->order;
        $vendor = $order?->vendor;
        $taxes = [];
        $qty = (float) $item->product_quantity;
        $unitPrice = (float) $item->unit_price;
        $subtotal = (float) $item->price_subtotal;
        $amount = (float) $item->price_tax;
        $rate = (float) ($item->tax?->tax_rate ?? 0);

        // Recalculate if unit_price was updated from inward/bill but item line totals were not recomputed
        if ($unitPrice > 0 && $qty > 0) {
            $discountVal = (float) ($item->discount_amount ?? 0);
            $subBefore = $qty * $unitPrice;
            $lineDiscount = ($item->discount_type === '%') ? ($subBefore * $discountVal) / 100 : $discountVal;
            $expectedSubtotal = max(0, $subBefore - $lineDiscount);
            if ($order?->tax_inclusive && $rate > 0) {
                $expectedSubtotal = round($expectedSubtotal / (1 + $rate / 100), 2);
                $expectedTax = round(($subBefore - $lineDiscount) - $expectedSubtotal, 2);
            } elseif ($rate > 0) {
                $expectedTax = round($expectedSubtotal * $rate / 100, 2);
            } else {
                $expectedTax = 0;
            }
            if (abs($subtotal - $expectedSubtotal) > 0.05) {
                $subtotal = $expectedSubtotal;
                $amount = $expectedTax;
            }
        }

        if ($rate <= 0 && $subtotal != 0) {
            $rate = round($amount / $subtotal * 100, 2);
        }
        $group = strtoupper(trim($item->tax?->tax_group ?? ''));
        $plantState = substr(trim($order?->plant?->gstin ?? ''), 0, 2);
        $vendorState = substr(trim($vendor?->gstin ?? ''), 0, 2);
        $inter = $plantState !== '' && $vendorState !== '' && $plantState !== $vendorState;
        if ($amount != 0) {
            // Explicit tax groups take precedence over the supplier's current state.
            $type = match (true) {
                str_contains($group, 'CGST') => 'CGST',
                str_contains($group, 'UTGST'), str_contains($group, 'UGST') => 'UTGST',
                str_contains($group, 'SGST') => 'SGST',
                str_contains($group, 'IGST') => 'IGST',
                $group !== '' && $group !== 'GST' => str_replace('_', ' ', $group),
                $inter => 'IGST',
                default => 'GST',
            };
            if ($type === 'GST') {
                $halfRate = number_format($rate / 2, 2, '.', '');
                $taxes['CGST_'.$halfRate] = round($amount / 2, 2);
                // Preserve the stored tax total even when it contains an odd paisa.
                $taxes['SGST_'.$halfRate] = round($amount - $taxes['CGST_'.$halfRate], 2);
            } else {
                $taxes[$type.'_'.number_format($rate, 2, '.', '')] = $amount;
            }
        }
        $sum = fn ($types) => array_sum(array_filter($taxes, fn ($key) => in_array(explode('_', $key)[0], $types), ARRAY_FILTER_USE_KEY));

        $bill = $order?->billingInvoice ?? $order?->bill;
        $billNo = $order?->bill_number;
        if (empty($billNo) && $bill) {
            $billNo = $bill->full_number ?: $bill->invoice_number;
        }
        if (empty($billNo)) {
            $billNo = $order?->po_number ?? '';
        }

        $rawBillDate = $order?->billed_date ?? $bill?->invoice_date ?? $order?->date_order ?? $order?->created_at;
        $billDate = $rawBillDate instanceof \DateTimeInterface
            ? $rawBillDate->format('Y-m-d')
            : ($rawBillDate ? \Illuminate\Support\Carbon::parse($rawBillDate)->toDateString() : '');

        return [
            'id' => $item->id,
            'document_id' => $item->order_id,
            'bill_no' => $billNo,
            'po_number' => $order?->po_number ?? '',
            'bill_date' => $billDate,
            'supplier_name' => $vendor?->legal_name ?? 'N/A',
            'gst_number' => $vendor?->gstin ?? '',
            'product_name' => $item->product?->title ?? $item->description ?? 'N/A',
            'document_type' => 'Purchase',
            'document_status' => $order?->state === 'billed' ? 'Billed' : ($order?->billing_status ?: ($order?->state ?? '')),
            'created_by' => $order?->creator?->email ?? $order?->creator?->username ?? '',
            'hsn_code' => $item->product?->hsn_code ?? $item->hsn_code ?? '',
            'unit' => $item->uom?->unit_code ?: ($item->uom?->unit_name ?? ''),
            'qty' => $qty,
            'purchase_rate' => $unitPrice,
            'taxable_amount' => $subtotal,
            'tax_amount' => $amount,
            'cgst' => round($sum(['CGST']), 2),
            'sgst' => round($sum(['SGST']), 2),
            'utgst' => round($sum(['UTGST']), 2),
            'igst' => round($sum(['IGST']), 2),
            'taxes' => $taxes,
            'discount' => (float) ($order?->discount_amount ?? 0),
            'roundoff' => (float) ($order?->rounding_value ?? 0),
            'net_amount' => round($subtotal + $amount, 2),
        ];
    }
}
