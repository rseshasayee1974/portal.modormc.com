<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PurchaseRegisterExport
{
    protected Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * Collect all unique tax columns from the entire matching dataset.
     */
    public function collectAllTaxColumns(string $plantState): array
    {
        $subQuery = (clone $this->query)->select('mm_purchase_order_items.id');

        $taxes = DB::table('mm_purchase_order_items')
            ->join('mm_taxes', 'mm_purchase_order_items.tax_id', '=', 'mm_taxes.id')
            ->join('mm_purchase_orders', 'mm_purchase_order_items.order_id', '=', 'mm_purchase_orders.id')
            ->join('mm_patrons', 'mm_purchase_orders.vendor_id', '=', 'mm_patrons.id')
            ->whereIn('mm_purchase_order_items.id', $subQuery)
            ->selectRaw('mm_taxes.tax_name, mm_taxes.tax_rate, mm_taxes.tax_group, mm_patrons.gstin')
            ->distinct()
            ->get();

        $keys = [];
        foreach ($taxes as $tax) {
            $taxRate  = (float) $tax->tax_rate;
            $taxGroup = strtoupper(trim($tax->tax_group ?? ''));
            if ($taxRate <= 0) continue;

            $vendorGstin = trim($tax->gstin ?? '');
            $isIntra = true;
            if (strlen($plantState) >= 2 && strlen($vendorGstin) >= 2) {
                if (substr($vendorGstin, 0, 2) !== $plantState) {
                    $isIntra = false;
                }
            }

            if ($isIntra) {
                if ($taxGroup === 'GST' || empty($taxGroup)) {
                    $halfRate = round($taxRate / 2, 2);
                    $keys['CGST_' . number_format($halfRate, 2, '.', '')] = ['type' => 'CGST', 'rate' => $halfRate];
                    $keys['SGST_' . number_format($halfRate, 2, '.', '')] = ['type' => 'SGST', 'rate' => $halfRate];
                } else {
                    $keys[$taxGroup . '_' . number_format($taxRate, 2, '.', '')] = ['type' => $taxGroup, 'rate' => $taxRate];
                }
            } else {
                if ($taxGroup === 'GST' || empty($taxGroup)) {
                    $keys['IGST_' . number_format($taxRate, 2, '.', '')] = ['type' => 'IGST', 'rate' => $taxRate];
                } else {
                    $keys[$taxGroup . '_' . number_format($taxRate, 2, '.', '')] = ['type' => $taxGroup, 'rate' => $taxRate];
                }
            }
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
        $sheet->setTitle("Purchase Register");

        // Plant state code for GST logic
        $plantId = Session::get('active_plant_id');
        $plantGstin = DB::table('mm_plants')->where('id', $plantId)->value('gstin');
        $plantState = $plantGstin && strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';

        // Headers
        $headers = [
            'Bill No',
            'Bill Date',
            'Supplier Name',
            'GST Number',
            'Product Name',
            'Qty',
            'Purchase Rate',
            'Taxable Amount',
            'CGST',
            'SGST',
            'IGST',
        ];

        // Fetch dynamic columns
        $taxColumns = $this->collectAllTaxColumns($plantState);
        foreach ($taxColumns as $col) {
            $headers[] = $col['label'];
        }

        $headers[] = 'Net Amount';

        // Write headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
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
        $this->query->chunk(1000, function ($items) use (&$sheet, &$rowNum, $taxColumns, $plantState, &$totalQty, &$totalTaxable, &$totalCgst, &$totalSgst, &$totalIgst, &$totalNet, &$dynamicTotals) {
            foreach ($items as $item) {
                $order = $item->order;
                $vendor = $order??->vendor;

                // Build rate-wise taxes for dynamic columns
                $rowTaxes = [];
                $cgst = 0.0;
                $sgst = 0.0;
                $igst = 0.0;

                $taxModel = $item->tax;
                $taxRate  = $taxModel ? (float) $taxModel->tax_rate : 0.0;
                $taxGroup = $taxModel ? strtoupper(trim($taxModel->tax_group ?? '')) : '';
                $priceTax = (float) $item->price_tax;

                if ($taxRate > 0 && $priceTax > 0) {
                    $isIntra = true;
                    $vendorGstin = $vendor ? trim($vendor->gstin ?? '') : '';

                    if (strlen($plantState) >= 2 && strlen($vendorGstin) >= 2) {
                        if (substr($vendorGstin, 0, 2) !== $plantState) {
                            $isIntra = false;
                        }
                    }

                    if ($isIntra) {
                        if ($taxGroup === 'GST' || empty($taxGroup)) {
                            $halfRate = round($taxRate / 2, 2);
                            $halfAmount = round($priceTax / 2, 2);

                            $cgst = $halfAmount;
                            $sgst = $halfAmount;

                            $rowTaxes['CGST_' . number_format($halfRate, 2, '.', '')] = $halfAmount;
                            $rowTaxes['SGST_' . number_format($halfRate, 2, '.', '')] = $halfAmount;
                        } else {
                            $rowTaxes[$taxGroup . '_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                        }
                    } else {
                        if ($taxGroup === 'GST' || empty($taxGroup)) {
                            $igst = $priceTax;
                            $rowTaxes['IGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                        } else {
                            $rowTaxes[$taxGroup . '_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                        }
                    }
                }

                $billNo = $order ? ($order->bill_number ?: $order->po_number) : '';
                $billDate = $order ? ($order->billed_date ? $order->billed_date->toDateString() : ($order->date_order ? $order->date_order->toDateString() : '')) : '';
                
                $colIdx = 1;
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $billNo);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $billDate);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $vendor ? $vendor->legal_name : 'N/A');
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $vendor ? $vendor->gstin : '');
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $item->product ? $item->product->title : ($item->description ?? 'N/A'));
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$item->product_quantity);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$item->unit_price);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$item->price_subtotal);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$cgst);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$sgst);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$igst);

                // Write dynamic columns
                foreach ($taxColumns as $col) {
                    $val = (float)($rowTaxes[$col['key']] ?? 0);
                    $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $val);
                    $dynamicTotals[$col['key']] = ($dynamicTotals[$col['key']] ?? 0) + $val;
                }

                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)$item->price_total);

                // Accumulate totals
                $totalQty += (float)$item->product_quantity;
                $totalTaxable += (float)$item->price_subtotal;
                $totalCgst += (float)$cgst;
                $totalSgst += (float)$sgst;
                $totalIgst += (float)$igst;
                $totalNet += (float)$item->price_total;

                $rowNum++;
            }
        });

        // Write Totals Row
        $colIdx = 1;
        $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, 'TOTAL');
        $sheet->setCellValueByColumnAndRow(6, $rowNum, $totalQty);
        $sheet->setCellValueByColumnAndRow(8, $rowNum, $totalTaxable);
        $sheet->setCellValueByColumnAndRow(9, $rowNum, $totalCgst);
        $sheet->setCellValueByColumnAndRow(10, $rowNum, $totalSgst);
        $sheet->setCellValueByColumnAndRow(11, $rowNum, $totalIgst);

        $colIdx = 12;
        foreach ($taxColumns as $col) {
            $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, (float)($dynamicTotals[$col['key']] ?? 0));
        }

        $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $totalNet);

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
        $spreadsheet->disconnectCells();
    }
}
