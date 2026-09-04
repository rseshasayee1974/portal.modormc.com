<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Services\Reports\ExcelExportService;

class SalesRegisterExport
{
    protected Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * Generate the Excel file and save it to the specified path.
     * Fetches data and totals directly from the DB.
     * 
     * Time Complexity: O(n) streaming.
     */
    public function export(string $filePath, string $period = 'All Dates'): void
    {
        // Headers (10 columns)
        $headers = [
            'Invoice No',
            'Invoice Date',
            'Customer Name',
            'GST Number',
            'Product Name',
            'Qty',
            // 'Rate',
            'Taxable Amount',
            // 'CGST',
            // 'SGST',
            // 'IGST',
            'Net Amount',
            'Payment Status',
        ];

        // Fetch totals directly from DB
        $subQuery = (clone $this->query)
            ->getQuery()
            ->cloneWithout(['orders', 'columns'])
            ->select('mm_invoice_items.id');

        $totals = DB::table('mm_invoice_items')
            ->whereIn('id', $subQuery)
            ->whereNull('deleted_at')
            ->selectRaw('
                COALESCE(SUM(quantity), 0)   AS total_qty,
                COALESCE(SUM(subtotal), 0)   AS total_taxable,
                COALESCE(SUM(line_total), 0) AS total_net
            ')
            ->first();

        $rows = [];

        // Process in chunks of 1000 rows
        $this->query->chunk(1000, function ($items) use (&$rows) {
            foreach ($items as $item) {
                $invoice = $item->invoice;
                $partner = $invoice?->partner;

                if (!$partner && $invoice && $invoice->invoice_label === 'Dispatch' && $invoice->ref_id) {
                    $dispatch = DB::table('mm_dispatches')->where('id', $invoice->ref_id)->first();
                    if ($dispatch) {
                        $customerId = $dispatch->customer_id;
                        if (!$customerId && !empty($dispatch->sales_order_id)) {
                            $so = DB::table('mm_sales_orders')->where('id', $dispatch->sales_order_id)->first();
                            $customerId = $so?->customer_id;
                        }
                        if ($customerId) {
                            $partner = \App\Models\Patron::withoutGlobalScopes()->find($customerId);
                        }
                    }
                }

                // Payment status
                $paymentStatus = 'Unpaid';
                if ($invoice) {
                    if ($invoice->status === 'paid' || $invoice->balance_amount <= 0) {
                        $paymentStatus = 'Paid';
                    } elseif ($invoice->paid_amount > 0 && $invoice->balance_amount > 0) {
                        $paymentStatus = 'Partial';
                    }
                }

                $invoiceNo = $invoice ? (($invoice->prefix ?? '') . ($invoice->invoice_number ?? '')) : '';
                
                $rows[] = [
                    $invoiceNo,
                    $invoice && $invoice->invoice_date ? $invoice->invoice_date->toDateString() : '',
                    $partner ? $partner->legal_name : 'N/A',
                    $partner ? ($partner->gstin ?: '') : '',
                    $item->item_name ?? 'N/A',
                    (float) $item->quantity,
                    // (float) $item->price_unit,
                    (float) $item->subtotal,
                    // (float) $cgst,
                    // (float) $sgst,
                    // (float) $igst,
                    (float) $item->line_total,
                    $paymentStatus,
                ];
            }
        });

        // Totals Row (matching exactly 9 columns)
        $totalRow = [
            'TOTAL',
            '', '', '', '',
            (float) ($totals->total_qty ?? 0),
            // '', // Rate column commented out
            (float) ($totals->total_taxable ?? 0),
            // CGST, SGST, IGST commented out
            (float) ($totals->total_net ?? 0),
            '',
        ];

        /** @var ExcelExportService $excelService */
        $excelService = app(ExcelExportService::class);
        $spreadsheet = $excelService->export('SALES REGISTER', $period, $headers, $rows, $totalRow);

        // Save
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
    }
}
