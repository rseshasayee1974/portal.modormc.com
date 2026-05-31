<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Dispatch;
use App\Models\Patron;
use App\Services\PlantContextService;

class SalesReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId  = $this->ctx->requirePlantId();
        $patronId = $params['patron_id'] ?? null;
        $start    = $params['start'];
        $end      = $params['end'];

        // 1. Invoice-level transactions
        $invoiceQuery = Invoice::with(['partner'])
            ->where('plant_id', $plantId)
            ->whereBetween('invoice_date', [$start, $end]);
        if ($patronId) $invoiceQuery->where('partner_id', $patronId);

        $invoicesList = $invoiceQuery->orderBy('invoice_date')->orderBy('invoice_number')->get();

        $transactions = $invoicesList->map(fn($inv) => [
            'date'           => $inv->invoice_date->toDateString(),
            'voucher_type'   => 'SALES',
            'voucher_no'     => ($inv->prefix ?? '') . ($inv->invoice_number ?? ''),
            'invoice_number' => ($inv->prefix ?? '') . ($inv->invoice_number ?? ''),
            'customer_name'  => $inv->partner?->legal_name ?? 'N/A',
            'narration'      => '[' . ($inv->partner?->legal_name ?? 'Customer') . '] Sales Invoice',
            'amount'         => (float)$inv->total_amount,
            'amount_total'   => (float)$inv->total_amount,
            'amount_untaxed' => (float)$inv->subtotal,
            'amount_tax'     => (float)$inv->tax_amount,
            'type'           => 'Cr',
            'debit'          => 0,
            'credit'         => (float)$inv->total_amount,
        ]);

        // 2. Product-wise consolidated
        $groupedProducts = InvoiceItem::whereHas('invoice', function ($q) use ($plantId, $start, $end, $patronId) {
            $q->where('plant_id', $plantId)->whereBetween('invoice_date', [$start, $end]);
            if ($patronId) $q->where('partner_id', $patronId);
        })->with(['uom'])->get()
            ->groupBy('item_name')
            ->map(function ($items) {
                $first    = $items->first();
                $totalQty = (float)$items->sum('quantity');
                $untaxed  = (float)$items->sum('subtotal');
                return [
                    'product_name'   => $first->item_name ?? 'Unknown Item',
                    'uom'            => $first->uom?->name ?? $first->uom?->code ?? 'Unit',
                    'quantity'       => $totalQty,
                    'avg_rate'       => $totalQty > 0 ? $untaxed / $totalQty : 0.0,
                    'amount_untaxed' => $untaxed,
                    'amount_tax'     => (float)$items->sum('line_tax_amount'),
                    'amount_total'   => (float)$items->sum('line_total'),
                ];
            })->values()->sortBy('product_name')->values();

        // 3. Dispatch summaries
        $dispatchQuery = Dispatch::with(['customer', 'mixDesign.unit'])
            ->where('plant_id', $plantId)
            ->whereBetween('dispatch_time', [$start . ' 00:00:00', $end . ' 23:59:59']);
        if ($patronId) $dispatchQuery->where('customer_id', $patronId);
        $dispatches = $dispatchQuery->get();

        $mixDesignSummary = $dispatches->groupBy('mixdesign_id')->map(function ($items) {
            $first    = $items->first();
            $totalQty = (float)$items->sum('delivered_qty');
            $untaxed  = (float)$items->sum('load_untax_amount');
            return [
                'mix_name'       => $first->mixDesign?->design_name ?? 'Unknown Mix',
                'concrete_grade' => $first->mixDesign?->grade ?? $first->mixDesign?->design_type ?? 'N/A',
                'uom'            => $first->mixDesign?->unit?->name ?? 'm³',
                'quantity'       => $totalQty,
                'avg_rate'       => $totalQty > 0 ? $untaxed / $totalQty : 0.0,
                'amount_untaxed' => $untaxed,
                'amount_tax'     => (float)$items->sum('load_tax_amount'),
                'amount_total'   => (float)$items->sum('load_total_amount'),
            ];
        })->values()->sortBy('mix_name')->values();

        $partySummary = $dispatches->groupBy('customer_id')->map(function ($items) {
            $first = $items->first();
            return [
                'party_name'     => $first->customer?->legal_name ?? 'Unknown Customer',
                'quantity'       => (float)$items->sum('delivered_qty'),
                'amount_untaxed' => (float)$items->sum('load_untax_amount'),
                'amount_tax'     => (float)$items->sum('load_tax_amount'),
                'amount_total'   => (float)$items->sum('load_total_amount'),
            ];
        })->values()->sortBy('party_name')->values();

        return [
            'opening_balance'        => 0,
            'transactions'           => $transactions,
            'total_untaxed'          => (float)$invoicesList->sum('subtotal'),
            'total_tax'              => (float)$invoicesList->sum('tax_amount'),
            'total_amount'           => (float)$invoicesList->sum('total_amount'),
            'product_summary'        => $groupedProducts,
            'total_quantity'         => (float)$groupedProducts->sum('quantity'),
            'total_product_untaxed'  => (float)$groupedProducts->sum('amount_untaxed'),
            'total_product_tax'      => (float)$groupedProducts->sum('amount_tax'),
            'total_product_amount'   => (float)$groupedProducts->sum('amount_total'),
            'mix_design_summary'     => $mixDesignSummary,
            'total_dispatch_quantity'=> (float)$mixDesignSummary->sum('quantity'),
            'total_dispatch_untaxed' => (float)$mixDesignSummary->sum('amount_untaxed'),
            'total_dispatch_tax'     => (float)$mixDesignSummary->sum('amount_tax'),
            'total_dispatch_amount'  => (float)$mixDesignSummary->sum('amount_total'),
            'party_summary'          => $partySummary,
            'total_party_quantity'   => (float)$partySummary->sum('quantity'),
            'total_party_untaxed'    => (float)$partySummary->sum('amount_untaxed'),
            'total_party_tax'        => (float)$partySummary->sum('amount_tax'),
            'total_party_amount'     => (float)$partySummary->sum('amount_total'),
        ];
    }

    public function targetName(array $params): string
    {
        return isset($params['patron_id'])
            ? (Patron::find($params['patron_id'])?->legal_name ?? 'Customer')
            : 'All Customer Sales';
    }
}
