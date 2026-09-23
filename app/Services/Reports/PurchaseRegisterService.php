<?php

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Builder;

class PurchaseRegisterService extends RegisterReportService
{
    protected function reportType(): string { return 'purchase_register'; }
    protected function query(array $filters): Builder { return $this->repository->getPurchaseRegisterQuery($filters); }
    protected function mapRow($item): array { return $this->mapPurchaseRow($item); }

    public function mapPurchaseRow($item): array
    {
        $order = $item->order;
        $vendor = $order?->vendor;
        $taxes = [];
        $amount = (float) $item->price_tax;
        $rate = (float) ($item->tax?->tax_rate ?? 0);
        if ($rate <= 0 && (float) $item->price_subtotal != 0) {
            $rate = round($amount / (float) $item->price_subtotal * 100, 2);
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

        return [
            'id' => $item->id,
            'document_id' => $item->order_id,
            'bill_no' => $order?->bill_number ?: ($order?->po_number ?? ''),
            'po_number' => $order?->po_number ?? '',
            'bill_date' => ($order?->billed_date ?? $order?->date_order ?? $order?->created_at)?->toDateString() ?? '',
            'supplier_name' => $vendor?->legal_name ?? 'N/A',
            'gst_number' => $vendor?->gstin ?? '',
            'product_name' => $item->product?->title ?? $item->description ?? 'N/A',
            'document_type' => 'Purchase',
            'document_status' => $order?->state ?? '',
            'created_by' => $order?->creator?->email ?? $order?->creator?->username ?? '',
            'hsn_code' => $item->hsn_code ?? '',
            'unit' => $item->uom?->unit_code ?: ($item->uom?->unit_name ?? ''),
            'qty' => (float) $item->product_quantity,
            'purchase_rate' => (float) $item->unit_price,
            'taxable_amount' => (float) $item->price_subtotal,
            'tax_amount' => $amount,
            'cgst' => round($sum(['CGST']), 2),
            'sgst' => round($sum(['SGST']), 2),
            'utgst' => round($sum(['UTGST']), 2),
            'igst' => round($sum(['IGST']), 2),
            'taxes' => $taxes,
            'net_amount' => (float) $item->price_total,
        ];
    }
}
