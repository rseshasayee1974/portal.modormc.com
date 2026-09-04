<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Services\Reports\ExcelExportService;

class PurchaseRegisterExport
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
        // Headers (8 columns)
        $headers = [
            'Bill No',
            'Bill Date',
            'Supplier Name',
            'GST Number',
            'Product Name',
            'Qty',
            // 'Purchase Rate',
            'Taxable Amount',
            // 'CGST',
            // 'SGST',
            // 'IGST',
            'Net Amount',
        ];

        // Fetch totals directly from DB
        $subQuery = (clone $this->query)
            ->getQuery()
            ->cloneWithout(['orders', 'columns'])
            ->select('mm_purchase_order_items.id');

        $totals = DB::table('mm_purchase_order_items')
            ->whereIn('id', $subQuery)
            ->whereNull('deleted_at')
            ->selectRaw('
                COALESCE(SUM(product_quantity), 0) AS total_qty,
                COALESCE(SUM(price_subtotal), 0)   AS total_taxable,
                COALESCE(SUM(price_total), 0)      AS total_net
            ')
            ->first();

        $rows = [];

        // Process in chunks of 1000 rows
        $this->query->chunk(1000, function ($items) use (&$rows) {
            foreach ($items as $item) {
                $order = $item->order;
                $vendor = $order?->vendor;

                $billNo = $order ? ($order->bill_number ?: $order->po_number) : '';
                $billDate = $order ? ($order->billed_date ? $order->billed_date->toDateString() : ($order->date_order ? $order->date_order->toDateString() : '')) : '';
                
                $rows[] = [
                    $billNo,
                    $billDate,
                    $vendor ? $vendor->legal_name : 'N/A',
                    $vendor ? ($vendor->gstin ?: '') : '',
                    $item->product ? $item->product->title : ($item->description ?? 'N/A'),
                    (float) $item->product_quantity,
                    // (float) $item->unit_price,
                    (float) $item->price_subtotal,
                    // (float) $cgst,
                    // (float) $sgst,
                    // (float) $igst,
                    (float) $item->price_total,
                ];
            }
        });

        // Totals Row (matching exactly 8 columns)
        $totalRow = [
            'TOTAL', '', '', '', '',
            (float) ($totals->total_qty ?? 0),
            // '', // Rate commented out
            (float) ($totals->total_taxable ?? 0),
            // CGST, SGST, IGST commented out
            (float) ($totals->total_net ?? 0),
        ];

        /** @var ExcelExportService $excelService */
        $excelService = app(ExcelExportService::class);
        $spreadsheet = $excelService->export('PURCHASE REGISTER', $period, $headers, $rows, $totalRow);

        // Save
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
    }
}
