<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\OrderTax;
use App\Services\PlantContextService;

class Gstr1ReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $plantGstin = \App\Models\Plant::where('id', $plantId)->value('gstin');
        $plantStateCode = $plantGstin ? substr($plantGstin, 0, 2) : '33';

        // Query all sales invoices, credit notes, and debit notes
        $invoices = Invoice::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with(['partner.addresses.state'])
            ->whereIn('invoice_type', ['sales', 'credit_note', 'debit_note'])
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('invoice_date', [$start, $end])
            ->get();

        $b2b = [];
        $b2c = [];
        $cdnr = [];
        $exp = [];

        foreach ($invoices as $inv) {
            $taxes = OrderTax::where('order_id', $inv->id)->where('order_type', 'Invoice')->whereNull('deleted_at')->get();
            $cgst  = (float) $taxes->filter(fn($t) => str_contains(strtoupper($t->name), 'CGST'))->sum('amount');
            $sgst  = (float) $taxes->filter(fn($t) => str_contains(strtoupper($t->name), 'SGST') || str_contains(strtoupper($t->name), 'UGST') || str_contains(strtoupper($t->name), 'UTGST'))->sum('amount');
            $igst  = (float) $taxes->filter(fn($t) => str_contains(strtoupper($t->name), 'IGST'))->sum('amount');

            $partner = $inv->partner;
            $gstin   = $partner ? trim($partner->gstin) : '';
            $pos     = $partner?->addresses?->first()?->state?->state_code ?? $plantStateCode;

            // Determine if credit/debit note
            if ($inv->invoice_type === 'credit_note' || $inv->invoice_type === 'debit_note') {
                $cdnr[] = [
                    'gstin'             => $gstin,
                    'customer_name'     => $partner?->legal_name ?? 'N/A',
                    'note_no'           => $inv->full_number,
                    'note_date'         => $inv->invoice_date->toDateString(),
                    'note_type'         => $inv->invoice_type === 'credit_note' ? 'C' : 'D',
                    'original_inv_no'   => $inv->ref_title ?: 'N/A',
                    'original_inv_date' => $inv->invoice_date->toDateString(),
                    'note_value'        => (float)$inv->total_amount,
                    'taxable_value'     => (float)$inv->subtotal,
                    'cgst'              => $cgst,
                    'sgst'              => $sgst,
                    'igst'              => $igst,
                    'place_of_supply'   => $pos,
                ];
            }
            // Check if export
            elseif (strtolower($inv->invoice_label ?? '') === 'export' || strtolower($inv->invoice_type ?? '') === 'export') {
                $exp[] = [
                    'export_type'     => $igst > 0 ? 'WPAY' : 'WOPAY',
                    'invoice_no'      => $inv->full_number,
                    'invoice_date'    => $inv->invoice_date->toDateString(),
                    'invoice_value'   => (float)$inv->total_amount,
                    'taxable_value'   => (float)$inv->subtotal,
                    'igst'            => $igst,
                    'place_of_supply' => '97', // International / Export Code
                ];
            }
            // Registered Customers (B2B)
            elseif (!empty($gstin)) {
                $b2b[] = [
                    'gstin'           => $gstin,
                    'customer_name'   => $partner?->legal_name ?? 'N/A',
                    'invoice_no'      => $inv->full_number,
                    'invoice_date'    => $inv->invoice_date->toDateString(),
                    'invoice_value'   => (float)$inv->total_amount,
                    'taxable_value'   => (float)$inv->subtotal,
                    'cgst'            => $cgst,
                    'sgst'            => $sgst,
                    'igst'            => $igst,
                    'place_of_supply' => $pos,
                ];
            }
            // Unregistered Customers (B2C)
            else {
                $b2c[] = [
                    'invoice_no'      => $inv->full_number,
                    'invoice_date'    => $inv->invoice_date->toDateString(),
                    'invoice_value'   => (float)$inv->total_amount,
                    'taxable_value'   => (float)$inv->subtotal,
                    'cgst'            => $cgst,
                    'sgst'            => $sgst,
                    'igst'            => $igst,
                    'place_of_supply' => $pos,
                ];
            }
        }

        return [
            'transactions' => [
                'b2b'  => $b2b,
                'b2c'  => $b2c,
                'cdnr' => $cdnr,
                'exp'  => $exp,
            ],
            'b2b'             => $b2b,
            'b2c'             => $b2c,
            'cdnr'            => $cdnr,
            'exp'             => $exp,
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'GSTR-1 Report';
    }
}
