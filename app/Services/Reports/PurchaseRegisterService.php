<?php

namespace App\Services\Reports;

use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRegisterService extends RegisterReportService
{
    protected function reportType(): string { return 'purchase_register'; }
    protected function query(array $filters): Builder { return $this->repository->getPurchaseRegisterQuery($filters); }
    protected function mapRow($item): array { return $this->mapBill($item); }

    public function buildReportFromQuery(Builder $query, array $filters = [], bool $allRows = true): array
    {
        $bills = (clone $query)->lazy(500)->map(fn ($item) => $this->mapRow($item));
        if (!empty($filters['gst_type'])) {
            $components = $filters['gst_type'] === 'inter' ? ['IGST'] : ['CGST', 'SGST', 'UTGST'];
            $bills = $bills->filter(fn ($row) => collect($row['taxes'])->contains(
                fn ($amount, $key) => $amount != 0 && in_array(explode('_', $key)[0], $components, true)));
        }
        $report = $this->buildFromRows($bills, $filters, $allRows);
        $report['note'] = 'For saved bills, Net Amount includes bill-level adjustments and rounding on the first bill item.';
        return $report;
    }

    private function mapBill(InvoiceItem $item): array
    {
        $bill = $item->invoice;
        $taxes = [];
        foreach ($item->orderTaxes as $split) {
            if ((int) $split->order_id !== (int) $bill->id) continue;
            $name = strtoupper(trim($split->name));
            preg_match('/CGST|UTGST|UGST|SGST|IGST|TCS/', $name, $match);
            $component = $match[0] ?? (str_replace('_', ' ', $name) ?: 'TAX');
            $key = ($component === 'UGST' ? 'UTGST' : $component).'_'.number_format($split->rate, 2, '.', '');
            $taxes[$key] = round(($taxes[$key] ?? 0) + $split->amount, 2);
        }
        // Independently rounded GST halves can differ from the billed tax by one paisa.
        $difference = round((float) $item->line_tax_amount - array_sum($taxes), 2);
        if ($taxes && abs($difference) === 0.01) {
            $last = array_key_last($taxes);
            $taxes[$last] = round($taxes[$last] + $difference, 2);
        }
        $first = (int) $item->id === (int) $bill->items_min_id;
        $adjustment = $first && $bill->total_amount !== null
            ? round((float) $bill->total_amount - (float) $bill->items_sum_line_total, 2) : 0;
        return [
            'id' => 'bill-item-'.$item->id, 'document_id' => 'bill-'.$bill->id,
            'supplier_id' => $bill->partner_id, 'bill_no' => $bill->full_number,
            'bill_date' => $bill->invoice_date?->toDateString(), 'po_number' => $bill->ref_title ?? '',
            'supplier_name' => $bill->partner?->legal_name ?? 'N/A', 'gst_number' => $bill->partner?->gstin ?? '',
            'document_type' => 'Bill', 'document_status' => $bill->status,
            'created_by' => $bill->creator?->email ?? $bill->creator?->username ?? '',
            'product_name' => $item->item_name, 'hsn_code' => $item->hsn_code,
            'unit' => $item->uom?->unit_code ?: ($item->uom?->unit_name ?? ''),
            'qty' => (float) $item->quantity, 'purchase_rate' => (float) $item->price_unit,
            'taxable_amount' => (float) $item->subtotal, 'tax_amount' => (float) $item->line_tax_amount,
            'net_amount' => round((float) $item->line_total + $adjustment, 2), 'taxes' => $taxes,
            'discount' => (float) $bill->discount_total,
            'roundoff' => $first ? (float) $bill->round_off : 0,
        ];
    }

}
