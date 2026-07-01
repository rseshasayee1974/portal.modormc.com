<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesRegisterExport
{
    protected Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * Collect all unique tax columns from the entire matching dataset.
     */
    public function collectAllTaxColumns(): array
    {
        $subQuery = (clone $this->query)->select('mm_invoice_items.id');

        $taxes = DB::table('mm_order_taxes')
            ->where('order_type', 'Invoice')
            ->whereIn('order_items_id', $subQuery)
            ->selectRaw('name, rate')
            ->distinct()
            ->get();

        $keys = [];
        foreach ($taxes as $tax) {
            $rawName = strtoupper(trim($tax->name ?? ''));
            $rate    = round((float) $tax->rate, 2);

            if (str_contains($rawName, 'CGST')) {
                $baseType = 'CGST';
            } elseif (str_contains($rawName, 'UTGST') || str_contains($rawName, 'UGST')) {
                $baseType = 'UTGST';
            } elseif (str_contains($rawName, 'SGST')) {
                $baseType = 'SGST';
            } elseif (str_contains($rawName, 'IGST')) {
                $baseType = 'IGST';
            } else {
                $baseType = $rawName ?: 'TAX';
            }

            $colKey = $baseType . '_' . number_format($rate, 2, '.', '');
            $keys[$colKey] = ['key' => $colKey, 'type' => $baseType, 'rate' => $rate];
        }

        $sortedKeys = array_keys($keys);
        usort($sortedKeys, function ($a, $b) {
            $order = ['CGST' => 0, 'SGST' => 1, 'UTGST' => 2, 'IGST' => 3];
            $typeA = explode('_', $a)[0];
            $typeB = explode('_', $b)[0];
            $oA = $order[$typeA] ?? 9;
            $oB = $order[$typeB] ?? 9;
            if ($oA !== $oB) return $oA - $oB;
            return strcmp($a, $b);
        });

        $taxColumns = [];
        foreach ($sortedKeys as $key) {
            $type = $keys[$key]['type'];
            $rateFloat = $keys[$key]['rate'];
            $rateLabel  = ($rateFloat == floor($rateFloat)) ? (int) $rateFloat . '%' : $rateFloat . '%';
            $taxColumns[] = [
                'key' => $key,
                'label' => $type . ' ' . $rateLabel
            ];
        }

        return $taxColumns;
    }

    /**
     * Generate the Excel file and save it to the specified path.
     * 
     * Time Complexity: O(n) for processing n rows.
     */
    public function export(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator("Modormc ERP");
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Sales Register");

        // Headers
        $headers = [
            'Invoice No',
            'Invoice Date',
            'Customer Name',
            'GST Number',
            'Product Name',
            'Qty',
            'Rate',
            'Taxable Amount',
            'CGST',
            'SGST',
            'IGST',
        ];

        // Fetch dynamic columns
        $taxColumns = $this->collectAllTaxColumns();
        foreach ($taxColumns as $col) {
            $headers[] = $col['label'];
        }

        $headers[] = 'Net Amount';
        $headers[] = 'Payment Status';

        // Write headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $this->setCell($sheet, $colIndex, 1, $header);
            $colIndex++;
        }

        // Apply style to header row
        $lastHeaderLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastHeaderLetter}1")->getFont()->setBold(true);

        $rowNum = 2;
        $totalQty = 0;
        $totalTaxable = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;
        $totalNet = 0;
        $dynamicTotals = [];

        // Process in chunks of 1000 rows
        $this->query->chunk(1000, function ($items) use (&$sheet, &$rowNum, $taxColumns, &$totalQty, &$totalTaxable, &$totalCgst, &$totalSgst, &$totalIgst, &$totalNet, &$dynamicTotals) {
            foreach ($items as $item) {
                $invoice = $item->invoice;
                $partner = $invoice?->partner;

                $cgst = $item->itemTaxes->where('name', 'LIKE', '%CGST%')->sum('amount');
                $sgst = $item->itemTaxes->where('name', 'LIKE', '%SGST%')->sum('amount') + $item->itemTaxes->where('name', 'LIKE', '%UTGST%')->sum('amount');
                $igst = $item->itemTaxes->where('name', 'LIKE', '%IGST%')->sum('amount');

                // Build rate-wise taxes for dynamic columns
                $rowTaxes = [];
                foreach ($item->itemTaxes as $tax) {
                    $rawName = strtoupper(trim($tax->name ?? ''));
                    $rate    = round((float) $tax->rate, 2);
                    $amount  = (float) $tax->amount;

                    if (str_contains($rawName, 'CGST')) {
                        $baseType = 'CGST';
                    } elseif (str_contains($rawName, 'UTGST') || str_contains($rawName, 'UGST')) {
                        $baseType = 'UTGST';
                    } elseif (str_contains($rawName, 'SGST')) {
                        $baseType = 'SGST';
                    } elseif (str_contains($rawName, 'IGST')) {
                        $baseType = 'IGST';
                    } else {
                        $baseType = $rawName ?: 'TAX';
                    }

                    $colKey = $baseType . '_' . number_format($rate, 2, '.', '');
                    $rowTaxes[$colKey] = ($rowTaxes[$colKey] ?? 0) + $amount;
                }

                // Determine payment status string
                $paymentStatus = 'Unpaid';
                if ($invoice) {
                    if ($invoice->status === 'paid' || $invoice->balance_amount <= 0) {
                        $paymentStatus = 'Paid';
                    } elseif ($invoice->paid_amount > 0 && $invoice->balance_amount > 0) {
                        $paymentStatus = 'Partial';
                    }
                }

                $invoiceNo = $invoice ? (($invoice->prefix ?? '') . ($invoice->invoice_number ?? '')) : '';
                
                $colIdx = 1;
                $this->setCell($sheet, $colIdx++, $rowNum, $invoiceNo);
                $this->setCell($sheet, $colIdx++, $rowNum, $invoice ? $invoice->invoice_date->toDateString() : '');
                $this->setCell($sheet, $colIdx++, $rowNum, $partner ? $partner->legal_name : 'N/A');
                $this->setCell($sheet, $colIdx++, $rowNum, $partner ? $partner->gstin : '');
                $this->setCell($sheet, $colIdx++, $rowNum, $item->item_name ?? 'N/A');
                $this->setCell($sheet, $colIdx++, $rowNum, (float)$item->quantity);
                $this->setCell($sheet, $colIdx++, $rowNum, (float)$item->price_unit);
                $this->setCell($sheet, $colIdx++, $rowNum, (float)$item->subtotal);
                $this->setCell($sheet, $colIdx++, $rowNum, (float)$cgst);
                $this->setCell($sheet, $colIdx++, $rowNum, (float)$sgst);
                $this->setCell($sheet, $colIdx++, $rowNum, (float)$igst);

                // Write dynamic columns
                foreach ($taxColumns as $col) {
                    $val = (float)($rowTaxes[$col['key']] ?? 0);
                    $this->setCell($sheet, $colIdx++, $rowNum, $val);
                    $dynamicTotals[$col['key']] = ($dynamicTotals[$col['key']] ?? 0) + $val;
                }

                $this->setCell($sheet, $colIdx++, $rowNum, (float)$item->line_total);
                $this->setCell($sheet, $colIdx++, $rowNum, $paymentStatus);

                // Accumulate totals
                $totalQty += (float)$item->quantity;
                $totalTaxable += (float)$item->subtotal;
                $totalCgst += (float)$cgst;
                $totalSgst += (float)$sgst;
                $totalIgst += (float)$igst;
                $totalNet += (float)$item->line_total;

                $rowNum++;
            }
        });

        // Write Totals Row
        $colIdx = 1;
        $this->setCell($sheet, $colIdx++, $rowNum, 'TOTAL');
        $this->setCell($sheet, 6, $rowNum, $totalQty);
        $this->setCell($sheet, 8, $rowNum, $totalTaxable);
        $this->setCell($sheet, 9, $rowNum, $totalCgst);
        $this->setCell($sheet, 10, $rowNum, $totalSgst);
        $this->setCell($sheet, 11, $rowNum, $totalIgst);

        $colIdx = 12;
        foreach ($taxColumns as $col) {
            $this->setCell($sheet, $colIdx++, $rowNum, (float)($dynamicTotals[$col['key']] ?? 0));
        }

        $this->setCell($sheet, $colIdx++, $rowNum, $totalNet);

        // Styling the total row
        $sheet->getStyle("A{$rowNum}:{$lastHeaderLetter}{$rowNum}")->getFont()->setBold(true);

        // Auto-size columns
        for ($c = 1; $c <= count($headers); $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Save
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
    }

    private function setCell($sheet, int $colIndex, int $rowIndex, $value): void
    {
        $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
        $sheet->setCellValue($cellAddress, $value);
    }
}
