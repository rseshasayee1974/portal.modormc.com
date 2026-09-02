<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\OrderTax;
use App\Models\PurchaseOrder;
use App\Services\PlantContextService;
use Illuminate\Support\Facades\DB;

class Gstr3bReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $plantGstin = DB::table('mm_plants')->where('id', $plantId)->value('gstin');
        $plantState = $plantGstin && strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';

        // 1. OUTWARD SUPPLIES (Sales Invoices)
        $salesInvoices = Invoice::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where('invoice_type', 'sales')
            ->whereIn('status', ['approved', 'paid'])
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

        // 2. INWARD SUPPLIES & ELIGIBLE ITC (Purchase Orders/Bills)
        $purchases = PurchaseOrder::where('plant_id', $plantId)
            ->with(['vendor'])
            ->whereNull('deleted_at')
            ->whereBetween('date_order', [$start, $end])
            ->get();

        $table4 = [
            'import_goods'    => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'import_services' => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'reverse_charge'  => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'isd_itc'         => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            'other_itc'       => ['igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0], // All other ITC
        ];

        foreach ($purchases as $po) {
            $vendorGstin = $po->vendor ? trim($po->vendor->gstin) : '';
            $totalTax    = (float)$po->amount_tax;

            if ($totalTax <= 0) continue;

            $isInterstate = !empty($vendorGstin) && substr($vendorGstin, 0, 2) !== $plantState;

            if ($isInterstate) {
                $table4['other_itc']['igst'] += $totalTax;
            } else {
                $table4['other_itc']['cgst'] += $totalTax / 2;
                $table4['other_itc']['sgst'] += $totalTax / 2;
            }
        }

        return [
            'transactions' => [
                'table31' => $table31,
                'table4'  => $table4,
            ],
            'table31'         => $table31,
            'table4'          => $table4,
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'GSTR-3B Report';
    }
}
