<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\OrderTax;
use App\Services\PlantContextService;

class Gstr3bReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        // 1. OUTWARD SUPPLIES (Sales Invoices)
        $salesInvoices = Invoice::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereIn('invoice_type', \App\Support\InvoiceClassification::aliases('Invoice'))
            ->whereIn('status', ['Approved', 'approved', 'APPROVED', 'Paid', 'paid', 'PAID'])
            ->whereBetween('invoice_date', [$start, $end])
            ->get();

        $table31 = [
            'a' => ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // Outward taxable supplies (standard)
            'b' => ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // Zero rated (Exports)
            'c' => ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // Exempt/Nil rated
            'd' => ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // Inward reverse charge
            'e' => ['taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // Non-GST supplies
        ];

        foreach ($salesInvoices as $inv) {
            $taxes    = OrderTax::where('order_id', $inv->id)->where('order_type', 'Invoice')->whereNull('deleted_at')->get();
            $cgst     = (float) $taxes->filter(fn($t) => str_contains(strtoupper($t->name), 'CGST'))->sum('amount');
            $sgst     = (float) $taxes->filter(fn($t) => str_contains(strtoupper($t->name), 'SGST') || str_contains(strtoupper($t->name), 'UGST') || str_contains(strtoupper($t->name), 'UTGST'))->sum('amount');
            $igst     = (float) $taxes->filter(fn($t) => str_contains(strtoupper($t->name), 'IGST'))->sum('amount');
            $totalGst = $cgst + $sgst + $igst;

            $isExport = strtolower($inv->invoice_label ?? '') === 'export' || strtolower($inv->invoice_type ?? '') === 'export';

            if ($isExport) {
                $table31['b']['taxable'] += (float)$inv->subtotal;
                $table31['b']['igst']    += $igst;
            } elseif ($totalGst == 0) {
                $table31['c']['taxable'] += (float)$inv->subtotal;
            } else {
                $table31['a']['taxable'] += (float)$inv->subtotal;
                $table31['a']['igst']    += $igst;
                $table31['a']['cgst']    += $cgst;
                $table31['a']['sgst']    += $sgst;
            }
        }

        // Purchase orders and supplier state do not establish either GST heads or ITC eligibility.
        // Keep this legacy report a provisional book view; the compliance workspace handles review.
        $purchases = app(\App\Services\Gst\GstBooks::class)->range($plantId, substr($start, 0, 10), substr($end, 0, 10));

        $table4 = [
            'import_goods'    => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'import_services' => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'reverse_charge'  => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'isd_itc'         => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'other_itc'       => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // All other ITC
        ];

        $warnings = ['Draft book summary only. ITC eligibility, reversals, notes, RCM and payment setoff require the GST Reconciliation workspace.'];
        foreach ($purchases as $book) {
            if ($book['direction'] !== 'purchase') continue;
            foreach (['igst', 'cgst', 'sgst'] as $head) $table4['other_itc'][$head] += $book['amounts'][$head] / 100;
            foreach ($book['issues'] as $issue) $warnings[] = $book['source_key'].': '.$issue;
        }

        return [
            'transactions' => [
                'table31' => $table31,
                'table4'  => $table4,
            ],
            'table31'         => $table31,
            'table4'          => $table4,
            'is_provisional'  => true,
            'warnings'        => array_values(array_unique($warnings)),
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'GSTR-3B Report';
    }
}
