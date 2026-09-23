<?php

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Builder;

class SalesRegisterService extends RegisterReportService
{
    protected function reportType(): string { return 'sales_register'; }
    protected function query(array $filters): Builder { return $this->repository->getSalesRegisterQuery($filters); }
    protected function mapRow($item): array { return $this->mapSalesRow($item); }

    public function mapSalesRow($item): array
    {
        $invoice = $item->invoice;
        $partner = $invoice?->partner;

        $invoiceNo = $invoice
            ? $invoice->full_number
            : '';

        // Build rate-wise tax breakdown: ['CGST_9.00' => 450.00, 'SGST_9.00' => 450.00, ...]
        $taxes = [];
        $cgst  = 0.0;
        $sgst  = 0.0;
        $utgst = 0.0;
        $igst  = 0.0;

        foreach ($item->itemTaxes as $tax) {
            $rawName = strtoupper(trim($tax->name ?? ''));
            $rate    = round((float) $tax->rate, 2);
            $amount  = (float) $tax->amount;

            if (str_contains($rawName, 'CGST')) {
                $baseType = 'CGST';
                $cgst += $amount;
            } elseif (str_contains($rawName, 'UTGST') || str_contains($rawName, 'UGST')) {
                $baseType = 'UTGST';
                $utgst += $amount;
            } elseif (str_contains($rawName, 'SGST')) {
                $baseType = 'SGST';
                $sgst += $amount;
            } elseif (str_contains($rawName, 'IGST')) {
                $baseType = 'IGST';
                $igst += $amount;
            } elseif (str_contains($rawName, 'TCS')) {
                $baseType = 'TCS';
            } else {
                $baseType = str_replace('_', ' ', $rawName) ?: 'TAX';
            }

            $colKey = $baseType . '_' . number_format($rate, 2, '.', '');
            $taxes[$colKey] = round(($taxes[$colKey] ?? 0) + $amount, 2);
        }

        $paymentStatus = 'Unpaid';
        if ($invoice) {
            if (strtolower($invoice->status ?? '') === 'paid' || ($invoice->balance_amount !== null && (float) $invoice->balance_amount <= 0)) {
                $paymentStatus = 'Paid';
            } elseif ($invoice->paid_amount > 0 && $invoice->balance_amount > 0) {
                $paymentStatus = 'Partial';
            }
        }

        $unit = $item->uom?->unit_code ?: ($item->uom?->unit_name ?? '');
        $quantity = rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');
        $partyTypes = $partner?->patron_type ?? json_decode($item->register_party_type ?? '[]', true);

        return [
            'id'             => $item->id,
            'document_id'    => $item->invoice_id,
            'bill_no'        => $invoice?->invoice_number ?? '',
            'document_type'  => $invoice?->invoice_label ?? 'Sales',
            'document_status'=> $invoice?->status ?? '',
            'hsn_code'       => $item->hsn_code ?? '',
            'unit'           => $unit,
            'payment_mode'   => ucfirst(strtolower(trim($item->register_payment_mode ?? ''))),
            'tax_name'       => $this->taxName($taxes),
            'unloading'      => $item->register_unloading ?? '',
            'truck'          => $item->register_truck_number ?? '',
            'description'    => implode(',', [$invoiceNo, $item->item_name ?? '', $quantity.$unit]),
            'party_type'     => is_array($partyTypes) ? implode(', ', $partyTypes) : (string) $partyTypes,
            'tax_amount'     => (float) $item->line_tax_amount,
            'created_by'     => $invoice?->creator?->email ?? $invoice?->creator?->username ?? '',
            'irn'            => $invoice?->einvoiceRelation?->einv_irn ?? '',
            'einvoice_status'=> $invoice?->einvoiceRelation?->einv_status ?? '',
            'ack_date'       => $invoice?->einvoiceRelation?->einv_ack_date?->format('d-m-Y H:i') ?? '',
            'cancel_at'      => $invoice?->einvoiceRelation?->einv_cancel_at?->format('d-m-Y H:i') ?? '',
            'invoice_no'     => $invoiceNo,
            'invoice_date'   => $invoice?->invoice_date?->toDateString() ?? '',
            'customer_name'  => $partner?->legal_name ?? $item->register_customer_name ?? 'N/A',
            'gst_number'     => $partner?->gstin ?? $item->register_customer_gstin ?? '',
            'product_name'   => $item->item_name ?? 'N/A',
            'qty'            => (float) $item->quantity,
            'rate'           => (float) $item->price_unit,
            'taxable_amount' => (float) $item->subtotal,
            'cgst'           => round($cgst, 2),
            'sgst'           => round($sgst, 2),
            'utgst'          => round($utgst, 2),
            'igst'           => round($igst, 2),
            'taxes'          => $taxes,          // rate-wise: {CGST_9.00: 450, SGST_9.00: 450}
            'net_amount'     => (float) $item->line_total,
            'payment_status' => $paymentStatus,
        ];
    }

    private function taxName(array $taxes): string
    {
        // Describe the recorded tax rates, so later tax-master edits cannot change history.
        $rates = [];
        foreach (array_keys($taxes) as $key) {
            [$type, $rate] = explode('_', $key, 2);
            $rates[$type] = ($rates[$type] ?? 0) + (float) $rate;
        }
        if (isset($rates['CGST']) && (isset($rates['SGST']) || isset($rates['UTGST']))) {
            $gst = $rates['CGST'] + ($rates['SGST'] ?? 0) + ($rates['UTGST'] ?? 0);
            unset($rates['CGST'], $rates['SGST'], $rates['UTGST']);
            $rates = ['GST' => $gst] + $rates;
        }
        $labels = [];
        foreach ($rates as $type => $rate) $labels[] = $type.' '.(float) $rate.'%';
        return implode(' + ', $labels);
    }

}
