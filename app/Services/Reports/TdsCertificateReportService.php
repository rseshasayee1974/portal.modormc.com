<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Tax;
use App\Services\PlantContextService;

class TdsCertificateReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $start    = $params['start'];
        $end      = $params['end'];
        $patronId = $params['patron_id'] ?? null;

        if (!$patronId) {
            return [
                'transactions'    => [],
                'deductor'        => null,
                'deductee'        => null,
                'opening_balance' => 0
            ];
        }

        $plant  = Plant::with(['addresses.state'])->find($plantId);
        $patron = Patron::with(['addresses.state'])->find($patronId);

        $transactions = [];

        // 1. Check if Patron is a Vendor (TDS deducted by us on vendor bills)
        $purchaseTds = PurchaseOrder::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where('vendor_id', $patronId)
            ->where('tds_amount', '>', 0)
            ->whereBetween('date_order', [$start, $end])
            ->get();

        foreach ($purchaseTds as $po) {
            $tdsTax = Tax::find($po->tds_tax_id);
            $transactions[] = [
                'date'           => $po->date_order->toDateString(),
                'doc_no'         => $po->po_number,
                'doc_type'       => 'Purchase Bill',
                'taxable_amount' => (float)$po->amount_untaxed,
                'tds_section'    => $tdsTax ? ($tdsTax->tax_group === 'TDS' ? 'Section ' . ($tdsTax->tax_name) : $tdsTax->tax_name) : 'Section 194C/194Q',
                'tds_rate'       => $tdsTax ? (float)$tdsTax->tax_rate : 1.0,
                'tds_amount'     => (float)$po->tds_amount,
            ];
        }

        // 2. Check if Patron is a Customer (TDS deducted by customer on sales invoices)
        $salesTds = Invoice::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where('partner_id', $patronId)
            ->where('invoice_type', 'sales')
            ->where('tds_amount', '>', 0)
            ->whereBetween('invoice_date', [$start, $end])
            ->get();

        foreach ($salesTds as $inv) {
            $tdsTax = Tax::find($inv->tds_tax_id);
            $transactions[] = [
                'date'           => $inv->invoice_date->toDateString(),
                'doc_no'         => $inv->full_number,
                'doc_type'       => 'Sales Invoice',
                'taxable_amount' => (float)$inv->subtotal,
                'tds_section'    => $tdsTax ? ($tdsTax->tax_group === 'TDS' ? 'Section ' . ($tdsTax->tax_name) : $tdsTax->tax_name) : 'Section 194C/194Q',
                'tds_rate'       => $tdsTax ? (float)$tdsTax->tax_rate : 1.0,
                'tds_amount'     => (float)$inv->tds_amount,
            ];
        }

        return [
            'transactions'    => $transactions,
            'deductor'        => [
                'name'    => $plant?->name ?? 'N/A',
                'gstin'   => $plant?->gstin ?? 'N/A',
                'pan'     => $plant?->pan ?? ($plant?->gstin ? substr($plant->gstin, 2, 10) : 'N/A'),
                'address' => $plant?->addresses?->first()?->address_line_1 ?? 'N/A',
            ],
            'deductee'        => [
                'name'    => $patron?->legal_name ?? 'N/A',
                'gstin'   => $patron?->gstin ?? 'N/A',
                'pan'     => $patron?->pan_no ?? 'N/A',
                'address' => $patron?->addresses?->first()?->address_line_1 ?? 'N/A',
            ],
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'TDS Certificate Details';
    }
}
