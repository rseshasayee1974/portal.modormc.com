<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelExportService
{
    /**
     * Generate a beautifully formatted Spreadsheet instance based on the standard template.
     */
    public function export(string $title, string $period, array $headers, array $rows, ?array $totalRow = null, array $extraSections = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report Output');

        // Determine max column index
        $maxColIndex = count($headers) ?: 5;
        if (!empty($extraSections['tables'])) {
            foreach ($extraSections['tables'] as $table) {
                if (isset($table['headers'])) {
                    $maxColIndex = max($maxColIndex, count($table['headers']));
                }
            }
        }
        $maxColLetter = Coordinate::stringFromColumnIndex($maxColIndex);

        // 1. Merged Title Block
        $sheet->mergeCells("A1:{$maxColLetter}1");
        $sheet->setCellValue("A1", strtoupper($title));
        $sheet->getStyle("A1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D2D3E'] // Dark Navy Theme
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        $sheet->getRowDimension(1)->setRowHeight(35);

        // 2. Merged Period/Date Block
        $sheet->mergeCells("A2:{$maxColLetter}2");
        $sheet->setCellValue("A2", $period);
        $sheet->getStyle("A2")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => '475569']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'] // Light Gray/Cyan Background
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $currentRow = 4;

        // 3. Profiles Block (e.g. TDS Deductor / Deductee profiles)
        if (!empty($extraSections['profiles'])) {
            $profiles = $extraSections['profiles'];

            // Header titles
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $profiles['deductor_title'] ?? "TAX DEDUCTOR");
            $sheet->getStyle("A{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
            ]);

            $sheet->mergeCells("E{$currentRow}:G{$currentRow}");
            $sheet->setCellValue("E{$currentRow}", $profiles['deductee_title'] ?? "TAX DEDUCTEE");
            $sheet->getStyle("E{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1E3A8A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $currentRow++;

            // Profile info rows
            $deductorLines = $profiles['deductor_lines'] ?? [];
            $deducteeLines = $profiles['deductee_lines'] ?? [];
            $maxLines = max(count($deductorLines), count($deducteeLines));

            for ($i = 0; $i < $maxLines; $i++) {
                if (isset($deductorLines[$i])) {
                    $sheet->setCellValue("A{$currentRow}", $deductorLines[$i]);
                }
                if (isset($deducteeLines[$i])) {
                    $sheet->setCellValue("E{$currentRow}", $deducteeLines[$i]);
                }
                $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle("E{$currentRow}")->getFont()->setItalic(true)->setSize(9);
                $sheet->getRowDimension($currentRow)->setRowHeight(20);
                $currentRow++;
            }
            $currentRow++; // Blank row space
        }

        // 4. Standard Table Headers & Rows (Statement Result Grid / Primary Detailed Table)
        if (!empty($headers) || !empty($rows)) {
            if (!empty($headers)) {
                $sheet->fromArray($headers, null, "A{$currentRow}");
                $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(25);
                $currentRow++;
            }

            $colTypes = [];
            foreach ($rows as $rVal) {
                foreach ($rVal as $cIdx => $val) {
                    if ($val !== null && $val !== '') {
                        if (is_numeric($val) || is_float($val) || is_int($val)) {
                            $colTypes[$cIdx] = 'numeric';
                        } else {
                            if (($colTypes[$cIdx] ?? '') !== 'numeric') {
                                $colTypes[$cIdx] = 'string';
                            }
                        }
                    }
                }
            }

            $startDataRow = $currentRow;
            foreach ($rows as $rIdx => $rVal) {
                $formattedRow = [];
                foreach ($rVal as $cIdx => $val) {
                    if ($val === null || $val === '') {
                        $type = $colTypes[$cIdx] ?? 'string';
                        $formattedRow[] = ($type === 'numeric') ? 0 : '-';
                    } else {
                        $formattedRow[] = $val;
                    }
                }

                $sheet->fromArray($formattedRow, null, "A{$currentRow}");
                if ($rIdx % 2 === 1) {
                    $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']]
                    ]);
                }
                $sheet->getRowDimension($currentRow)->setRowHeight(20);
                $currentRow++;
            }
            $endDataRow = $currentRow - 1;

            // Optional Total Row
            $totalRowIndex = null;
            if (!empty($totalRow)) {
                $sheet->fromArray($totalRow, null, "A{$currentRow}");
                $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(22);
                $totalRowIndex = $currentRow;
                $currentRow++;
            }

            // Apply explicit number formatting & right alignment per column
            if (!empty($headers)) {
                foreach ($headers as $cIdx => $headerText) {
                    $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                    $formatCode = $this->getColumnFormatCode($headerText);
                    if ($formatCode !== null && $endDataRow >= $startDataRow) {
                        $sheet->getStyle("{$colLetter}{$startDataRow}:{$colLetter}{$endDataRow}")
                            ->getNumberFormat()->setFormatCode($formatCode);
                        $sheet->getStyle("{$colLetter}{$startDataRow}:{$colLetter}{$endDataRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    if ($totalRowIndex !== null && $formatCode !== null) {
                        $cellVal = $sheet->getCell("{$colLetter}{$totalRowIndex}")->getValue();
                        if ($cellVal !== null && $cellVal !== '' && is_numeric($cellVal)) {
                            $sheet->getStyle("{$colLetter}{$totalRowIndex}")
                                ->getNumberFormat()->setFormatCode($formatCode);
                            $sheet->getStyle("{$colLetter}{$totalRowIndex}")
                                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                    }
                }
            }

            $currentRow += 2; // Spacing before subsequent tables
        }

        // 5. Custom Structural / Consolidated Tables (e.g. Unload Site, Payment Mode, GSTR-3B)
        if (!empty($extraSections['tables'])) {
            foreach ($extraSections['tables'] as $table) {
                // Table Title
                $sheet->mergeCells("A{$currentRow}:{$maxColLetter}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $table['title']);
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $table['title_bg'] ?? '334155']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(25);
                $currentRow++;

                // Table Headers
                if (isset($table['headers'])) {
                    $sheet->fromArray($table['headers'], null, "A{$currentRow}");
                    $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '334155']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $table['header_bg'] ?? 'E2E8F0']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                    ]);
                    $sheet->getRowDimension($currentRow)->setRowHeight(22);
                    $currentRow++;
                }

                // Table Rows
                $tableStartDataRow = $currentRow;
                if (isset($table['rows'])) {
                    $colTypes = [];
                    foreach ($table['rows'] as $rVal) {
                        foreach ($rVal as $cIdx => $val) {
                            if ($val !== null && $val !== '') {
                                if (is_numeric($val) || is_float($val) || is_int($val)) {
                                    $colTypes[$cIdx] = 'numeric';
                                } else {
                                    if (($colTypes[$cIdx] ?? '') !== 'numeric') {
                                        $colTypes[$cIdx] = 'string';
                                    }
                                }
                            }
                        }
                    }

                    foreach ($table['rows'] as $rIdx => $rVal) {
                        $formattedRow = [];
                        foreach ($rVal as $cIdx => $val) {
                            if ($val === null || $val === '') {
                                $type = $colTypes[$cIdx] ?? 'string';
                                $formattedRow[] = ($type === 'numeric') ? 0 : '-';
                            } else {
                                $formattedRow[] = $val;
                            }
                        }

                        $sheet->fromArray($formattedRow, null, "A{$currentRow}");
                        if ($rIdx % 2 === 1) {
                            $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']]
                            ]);
                        }
                        $sheet->getRowDimension($currentRow)->setRowHeight(20);
                        $currentRow++;
                    }
                }
                $tableEndDataRow = $currentRow - 1;

                // Optional Total Row for extra table
                $tableTotalRowIndex = null;
                $tRowData = $table['totalRow'] ?? $table['total_row'] ?? null;
                if (!empty($tRowData)) {
                    $sheet->fromArray($tRowData, null, "A{$currentRow}");
                    $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                    ]);
                    $sheet->getRowDimension($currentRow)->setRowHeight(22);
                    $tableTotalRowIndex = $currentRow;
                    $currentRow++;
                }

                // Apply explicit formatting and right alignment for extra table columns
                if (isset($table['headers'])) {
                    foreach ($table['headers'] as $cIdx => $headerText) {
                        $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                        $formatCode = $this->getColumnFormatCode($headerText);
                        if ($formatCode !== null) {
                            if ($tableEndDataRow >= $tableStartDataRow) {
                                $sheet->getStyle("{$colLetter}{$tableStartDataRow}:{$colLetter}{$tableEndDataRow}")
                                    ->getNumberFormat()->setFormatCode($formatCode);
                                $sheet->getStyle("{$colLetter}{$tableStartDataRow}:{$colLetter}{$tableEndDataRow}")
                                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                            }
                            if ($tableTotalRowIndex !== null) {
                                $cellVal = $sheet->getCell("{$colLetter}{$tableTotalRowIndex}")->getValue();
                                if ($cellVal !== null && $cellVal !== '' && is_numeric($cellVal)) {
                                    $sheet->getStyle("{$colLetter}{$tableTotalRowIndex}")
                                        ->getNumberFormat()->setFormatCode($formatCode);
                                    $sheet->getStyle("{$colLetter}{$tableTotalRowIndex}")
                                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                                }
                            }
                        }
                    }
                }

                $currentRow += 2; // Spacing between tables
            }
        }

        // 7. Apply Flexible Column Auto-Sizing
        $highestColumn      = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // 8. Branded Footer Block
        $footerRow     = $currentRow + 2;
        $highestColumn = $sheet->getHighestColumn();
        $sheet->mergeCells("A{$footerRow}:{$highestColumn}{$footerRow}");
        $sheet->setCellValue("A{$footerRow}", "Powered by ModoRMC ERP - Modern Compliance System");
        $sheet->getStyle("A{$footerRow}")->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 9,
                'color' => ['rgb' => '475569'],
                'bold' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        $sheet->getRowDimension($footerRow)->setRowHeight(24);

        return $spreadsheet;
    }

    /**
     * Generate standard report spreadsheet from type, start, end and normalized data.
     */
    public function generateExcelReport(string $type, ?string $start, ?string $end, array $data): Spreadsheet
    {
        $title = strtoupper(str_replace('_', ' ', $type)) . " REPORT";
        $startLabel = $start ? (str_contains($start, ':') ? \Carbon\Carbon::parse($start)->format('d-m-Y H:i') : \Carbon\Carbon::parse($start)->format('d-m-Y')) : '';
        $endLabel   = $end ? (str_contains($end, ':') ? \Carbon\Carbon::parse($end)->format('d-m-Y H:i') : \Carbon\Carbon::parse($end)->format('d-m-Y')) : '';
        $period = ($startLabel && $endLabel) ? "Period: $startLabel to $endLabel" : ($startLabel ?: $endLabel ?: "All Dates");
        
        $headersList = [];
        $rows = [];
        $totalRow = null;
        $extraSections = [];

        if ($type === 'gstr3b') {
            $title = "GSTR-3B RETURN SUMMARY";
            $extraSections = [
                'tables' => [
                    [
                        'title' => 'Table 3.1: Details of Outward Supplies & Inward Supplies Liable to Reverse Charge',
                        'title_bg' => '0C4A6E',
                        'headers' => ['Nature of Supplies', 'Total Taxable Value', 'Integrated Tax (IGST)', 'Central Tax (CGST)', 'State/UT Tax (SGST)'],
                        'header_bg' => 'E0F2FE',
                        'rows' => [
                            ['(a) Outward Taxable Supplies (other than zero rated, nil rated, exempted)', $data['table31']['a']['taxable'], $data['table31']['a']['igst'], $data['table31']['a']['cgst'], $data['table31']['a']['sgst']],
                            ['(b) Outward Taxable Supplies (zero rated / exports)', $data['table31']['b']['taxable'], $data['table31']['b']['igst'], $data['table31']['b']['cgst'], $data['table31']['b']['sgst']],
                            ['(c) Other Outward Supplies (nil rated, exempted)', $data['table31']['c']['taxable'], $data['table31']['c']['igst'], $data['table31']['c']['cgst'], $data['table31']['c']['sgst']],
                            ['(d) Inward Supplies (liable to reverse charge)', $data['table31']['d']['taxable'], $data['table31']['d']['igst'], $data['table31']['d']['cgst'], $data['table31']['d']['sgst']],
                            ['(e) Non-GST Outward Supplies', $data['table31']['e']['taxable'], $data['table31']['e']['igst'], $data['table31']['e']['cgst'], $data['table31']['e']['sgst']],
                        ]
                    ],
                    [
                        'title' => 'Table 4: Eligible Input Tax Credit (ITC)',
                        'title_bg' => '065F46',
                        'headers' => ['Details', 'Integrated Tax (IGST)', 'Central Tax (CGST)', 'State/UT Tax (SGST)', ''],
                        'header_bg' => 'D1FAE5',
                        'rows' => [
                            ['(1) Import of goods', $data['table4']['import_goods']['igst'], $data['table4']['import_goods']['cgst'], $data['table4']['import_goods']['sgst']],
                            ['(2) Import of services', $data['table4']['import_services']['igst'], $data['table4']['import_services']['cgst'], $data['table4']['import_services']['sgst']],
                            ['(3) Inward supplies liable to reverse charge', $data['table4']['reverse_charge']['igst'], $data['table4']['reverse_charge']['cgst'], $data['table4']['reverse_charge']['sgst']],
                            ['(4) Inward supplies from ISD', $data['table4']['isd_itc']['igst'], $data['table4']['isd_itc']['cgst'], $data['table4']['isd_itc']['sgst']],
                            ['(5) All other ITC', $data['table4']['other_itc']['igst'], $data['table4']['other_itc']['cgst'], $data['table4']['other_itc']['sgst']],
                        ]
                    ]
                ]
            ];
        } elseif ($type === 'esi_pf_challan') {
            $title = "ESI & PF CHALLAN GENERATION SUMMARY";
            $extraSections = [
                'tables' => [
                    [
                        'title' => 'Provident Fund (PF) Challan Details',
                        'title_bg' => '1D2D3E',
                        'headers' => ['Employee Code', 'Employee Name', 'UAN', 'Gross Wages (₹)', 'EPF Wages (₹)', 'EPS Wages (₹)', 'Employee PF (12%)', 'Employer EPS (8.33%)', 'Employer EPF (3.67%)', 'Total PF Payable'],
                        'header_bg' => 'F1F5F9',
                        'rows' => collect($data['pf'] ?? [])->map(fn($r) => [
                            $r['employee_code'] ?? '',
                            $r['name'] ?? '',
                            $r['uan'] ?? '',
                            (float)($r['gross_wages'] ?? 0),
                            (float)($r['epf_wages'] ?? 0),
                            (float)($r['eps_wages'] ?? 0),
                            (float)($r['employee_contribution'] ?? 0),
                            (float)($r['employer_eps_share'] ?? 0),
                            (float)($r['employer_epf_share'] ?? 0),
                            (float)($r['total_contribution'] ?? 0)
                        ])->toArray()
                    ],
                    [
                        'title' => 'Employee State Insurance (ESI) Challan Details',
                        'title_bg' => '1D2D3E',
                        'headers' => ['Employee Code', 'Employee Name', 'ESI Number', 'Days Worked', 'Gross Wages (₹)', 'Employee ESI (0.75%)', 'Employer ESI (3.25%)', 'Total ESI Payable'],
                        'header_bg' => 'F1F5F9',
                        'rows' => collect($data['esi'] ?? [])->map(fn($r) => [
                            $r['employee_code'] ?? '',
                            $r['name'] ?? '',
                            $r['esi_number'] ?? '',
                            $r['days_worked'] ?? 0,
                            (float)($r['gross_wages'] ?? 0),
                            (float)($r['employee_contribution'] ?? 0),
                            (float)($r['employer_contribution'] ?? 0),
                            (float)($r['total_contribution'] ?? 0)
                        ])->toArray()
                    ]
                ]
            ];
        } elseif ($type === 'tds_certificate') {
            $title = "TDS CERTIFICATE STATEMENT";
            $headersList = ['Date', 'Document Number', 'Document Type', 'Taxable Amount', 'TDS Section', 'TDS Rate %', 'TDS Amount'];
            foreach (($data['transactions'] ?? []) as $row) {
                $rows[] = [
                    $row['date'] ?? '',
                    $row['doc_no'] ?? '',
                    $row['doc_type'] ?? '',
                    $row['taxable_amount'] ?? 0,
                    $row['tds_section'] ?? '',
                    $row['tds_rate'] ?? 0,
                    $row['tds_amount'] ?? 0
                ];
            }
            $totalTaxable = collect($data['transactions'] ?? [])->sum('taxable_amount');
            $totalTds = collect($data['transactions'] ?? [])->sum('tds_amount');
            $totalRow = ['Total Summary', '', '', $totalTaxable, '', '', $totalTds];
            $extraSections = [
                'profiles' => [
                    'deductor_title' => 'TAX DEDUCTOR',
                    'deductor_lines' => [
                        "Name: " . ($data['deductor']['name'] ?? 'N/A'),
                        "PAN: " . ($data['deductor']['pan'] ?? 'N/A'),
                        "GSTIN: " . ($data['deductor']['gstin'] ?? 'N/A')
                    ],
                    'deductee_title' => 'TAX DEDUCTEE',
                    'deductee_lines' => [
                        "Name: " . ($data['deductee']['name'] ?? 'N/A'),
                        "PAN: " . ($data['deductee']['pan'] ?? 'N/A'),
                        "GSTIN: " . ($data['deductee']['gstin'] ?? 'N/A')
                    ]
                ]
            ];
        } else {
            if ($type === 'silo_stock_valuation') {
                $headersList = ['Product Name', 'Category', 'UOM', 'Opening Qty', 'Opening Value', 'Inward Qty', 'Inward Value', 'Consumed Qty', 'Consumed Value (COGS)', 'Ending Qty', 'Ending Value', 'Avg Unit Cost'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['product_name'] ?? '',
                        $row['category'] ?? '',
                        $row['uom'] ?? '',
                        $row['opening_qty'] ?? 0,
                        $row['opening_value'] ?? 0,
                        $row['inward_qty'] ?? 0,
                        $row['inward_value'] ?? 0,
                        $row['consumed_qty'] ?? 0,
                        $row['consumed_value'] ?? 0,
                        $row['ending_qty'] ?? 0,
                        $row['ending_value'] ?? 0,
                        $row['avg_unit_cost'] ?? 0
                    ];
                }
            } elseif ($type === 'inventory_stock') {
                $headersList = ['Date', 'Product Name', 'UOM', 'Opening Qty', 'Current Stock'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['product_name'] ?? '',
                        $row['uom'] ?? '',
                        $row['opening_qty'] ?? 0,
                        $row['quantity'] ?? 0,
                    ];
                }
            } elseif ($type === 'inventory_inward') {
                $headersList = ['Received Date', 'Inward No', 'PO No', 'Supplier Name', 'Product', 'Quantity', 'Truck No'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['inward_no'] ?? '',
                        $row['po_number'] ?? '',
                        $row['vendor_name'] ?? '',
                        $row['product_name'] ?? '',
                        $row['quantity'] ?? 0,
                        $row['truck_no'] ?? ''
                    ];
                }
            } elseif ($type === 'production_batch') {
                $headersList = ['Start Date', 'Batch No', 'Sales Order', 'Mix Design', 'Batch Size (m³)', 'Operator', 'Status'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['batch_no'] ?? '',
                        $row['sales_order'] ?? '',
                        $row['mix_design'] ?? '',
                        $row['batch_size'] ?? 0,
                        $row['operator'] ?? '',
                        $row['status'] ?? ''
                    ];
                }
            } elseif ($type === 'machines_list') {
                $headersList = ['Registration', 'Vehicle Model', 'Vehicle Type', 'Make Year', 'Capacity', 'Owner'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['registration'] ?? '',
                        $row['vehicle_model'] ?? '',
                        $row['vehicle_type'] ?? '',
                        $row['make_year'] ?? '',
                        $row['capacity'] ?? '',
                        $row['owner'] ?? ''
                    ];
                }
            } elseif ($type === 'payroll_personnel') {
                $headersList = ['Name', 'Role / Employee Type', 'Joining Date', 'Status', 'Email', 'Phone'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['name'] ?? '',
                        $row['employee_type'] ?? '',
                        $row['joining_date'] ?? '',
                        $row['status'] ?? '',
                        $row['email'] ?? '',
                        $row['phone'] ?? ''
                    ];
                }
            } elseif ($type === 'gstr1') {
                $headersList = ['SECTION', 'Customer GSTIN', 'Customer Name', 'Invoice/Note No', 'Date', 'Type (Inv/Note)', 'Total Value', 'Taxable Value', 'CGST', 'SGST', 'IGST', 'POS'];
                foreach (($data['b2b'] ?? []) as $row) {
                    $rows[] = ['B2B', $row['gstin'] ?? '', $row['customer_name'] ?? '', $row['invoice_no'] ?? '', $row['invoice_date'] ?? '', 'Invoice', $row['invoice_value'] ?? 0, $row['taxable_value'] ?? 0, $row['cgst'] ?? 0, $row['sgst'] ?? 0, $row['igst'] ?? 0, $row['place_of_supply'] ?? ''];
                }
                foreach (($data['b2c'] ?? []) as $row) {
                    $rows[] = ['B2C', '', 'Unregistered Customer', $row['invoice_no'] ?? '', $row['invoice_date'] ?? '', 'Invoice', $row['invoice_value'] ?? 0, $row['taxable_value'] ?? 0, $row['cgst'] ?? 0, $row['sgst'] ?? 0, $row['igst'] ?? 0, $row['place_of_supply'] ?? ''];
                }
                foreach (($data['cdnr'] ?? []) as $row) {
                    $rows[] = ['CDNR', $row['gstin'] ?? '', $row['customer_name'] ?? '', $row['note_no'] ?? '', $row['note_date'] ?? '', $row['note_type'] ?? '', $row['note_value'] ?? 0, $row['taxable_value'] ?? 0, $row['cgst'] ?? 0, $row['sgst'] ?? 0, $row['igst'] ?? 0, $row['place_of_supply'] ?? ''];
                }
                foreach (($data['exp'] ?? []) as $row) {
                    $rows[] = ['EXP', '', 'Export Customer', $row['invoice_no'] ?? '', $row['invoice_date'] ?? '', $row['export_type'] ?? '', $row['invoice_value'] ?? 0, $row['taxable_value'] ?? 0, 0, 0, $row['igst'] ?? 0, $row['place_of_supply'] ?? ''];
                }
            } elseif ($type === 'purchase') {
                $title = "PURCHASE ORDERS & BILLS SUMMARY";
                $headersList = ['#', 'Date', 'PO Number', 'Supplier / Vendor', 'Taxable Amt', 'Tax Amt', 'Total Amt'];
                foreach (($data['transactions'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['date'] ?? '',
                        $row['po_number'] ?? '',
                        $row['vendor_name'] ?? '',
                        (float)($row['amount_untaxed'] ?? 0),
                        (float)($row['amount_tax'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Total Purchase', '', '',
                    (float)($data['total_untaxed'] ?? 0),
                    (float)($data['total_tax'] ?? 0),
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['product_summary'])) {
                    $prodRows = [];
                    foreach ($data['product_summary'] as $pi => $pRow) {
                        $prodRows[] = [
                            $pi + 1,
                            $pRow['product_name'] ?? '',
                            $pRow['uom'] ?? '',
                            (float)($pRow['quantity'] ?? 0),
                            (float)($pRow['avg_rate'] ?? 0),
                            (float)($pRow['amount_untaxed'] ?? 0),
                            (float)($pRow['amount_tax'] ?? 0),
                            (float)($pRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'MATERIAL WISE PURCHASE SUMMARY',
                        'headers' => ['#', 'Product Name', 'UOM', 'Quantity', 'Avg Rate', 'Taxable Amt', 'Tax Amt', 'Total Amt'],
                        'rows' => $prodRows,
                        'totalRow' => [
                            '', 'Total Summary', '',
                            (float)($data['total_product_quantity'] ?? $data['total_quantity'] ?? collect($data['product_summary'])->sum('quantity')),
                            '',
                            (float)($data['total_product_untaxed'] ?? collect($data['product_summary'])->sum('amount_untaxed')),
                            (float)($data['total_product_tax'] ?? collect($data['product_summary'])->sum('amount_tax')),
                            (float)($data['total_product_amount'] ?? collect($data['product_summary'])->sum('amount_total')),
                        ],
                    ];
                }
            } elseif ($type === 'product_consolidated') {
                $headersList = ['#', 'Mix Design Name', 'Grade', 'UOM', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty (m³)', 'Net Wt (T)', /* 'Avg Rate', */ 'Total Amt'];
                foreach (($data['transactions'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['mix_name'] ?? $row['product_name'] ?? '',
                        $row['concrete_grade'] ?? '',
                        $row['uom'] ?? '',
                        (int)($row['trips_count'] ?? 1),
                        // (float)($row['batch_size'] ?? 0),
                        (float)($row['quantity'] ?? 0),
                        (float)($row['netweight'] ?? 0),
                        // (float)($row['avg_rate'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Total Summary', '', '',
                    (int)($data['total_trips'] ?? 0),
                    // (float)($data['total_batch_size'] ?? 0),
                    (float)($data['total_quantity'] ?? 0),
                    (float)($data['total_net_weight'] ?? 0),
                    // '',
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['product_site_summary'])) {
                    $siteRows = [];
                    foreach ($data['product_site_summary'] as $si => $sRow) {
                        $siteRows[] = [
                            $si + 1,
                            $sRow['mix_name'] ?? '',
                            $sRow['concrete_grade'] ?? '',
                            $sRow['site_name'] ?? '',
                            (int)($sRow['trips_count'] ?? 1),
                            (float)($sRow['batch_size'] ?? 0),
                            (float)($sRow['quantity'] ?? 0),
                            (float)($sRow['netweight'] ?? 0),
                            (float)($sRow['avg_rate'] ?? 0),
                            (float)($sRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'UNLOAD SITE BASED PRODUCT CONSOLIDATED SUMMARY',
                        'headers' => ['#', 'Mix Design', 'Grade', 'Unloading Site', 'Trips', /*'Batch Size (m³)',*/ 'Delivered Qty (m³)', 'Net Wt (T)', 'Avg Rate', 'Total Amt'],
                        'rows' => $siteRows,
                    ];
                }

                if (!empty($data['payment_mode_summary'])) {
                    $pmRows = [];
                    foreach ($data['payment_mode_summary'] as $pi => $pRow) {
                        $pmRows[] = [
                            $pi + 1,
                            $pRow['payment_mode'] ?? '',
                            (int)($pRow['trips_count'] ?? 1),
                            // (float)($pRow['batch_size'] ?? 0),
                            (float)($pRow['quantity'] ?? 0),
                            (float)($pRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'PAYMENT MODE CONSOLIDATED SUMMARY',
                        'headers' => ['#', 'Payment Mode', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty (m³)', 'Total Amt'],
                        'rows' => $pmRows,
                    ];
                }

                if (!empty($data['batch_dispatches'])) {
                    $prodTripRows = [];
                    foreach ($data['batch_dispatches'] as $bi => $bRow) {
                        $prodTripRows[] = [
                            $bi + 1,
                            $bRow['dispatch_time'] ?? '',
                            $bRow['docket_no'] ?? $bRow['dispatch_no'] ?? '',
                            $bRow['mix_name'] ?? $bRow['concrete_grade'] ?? '',
                            $bRow['customer_name'] ?? '',
                            $bRow['site_name'] ?? '',
                            $bRow['truck_no'] ?? '',
                            (float)($bRow['batch_size'] ?? 0),
                            (float)($bRow['delivered_qty'] ?? 0),
                            (float)($bRow['amount_total'] ?? 0),
                        ];
                    }
                    // $extraSections['tables'][] = [
                    //     'title' => 'PRODUCT BATCHING / TRIP VERIFICATION LIST',
                    //     'headers' => ['Trip #', 'Date & Time', 'Dispatch No', 'Mix Design / Grade', 'Customer Name', 'Unloading Site', 'Truck / Mixer', 'Batch Size (m³)', 'Delivered Qty (m³)', 'Total Amt'],
                    //     'rows' => $prodTripRows,
                    // ];
                }
            } elseif ($type === 'customer_consolidated') {
                $headersList = ['#', 'Customer / Party Name', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty (m³)', /* 'Empty Wt (T)', 'Loaded Wt (T)', 'Net Wt (T)', 'Taxable Amt', 'Tax Amt', */ 'Total Amt'];
                foreach (($data['transactions'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['party_name'] ?? $row['customer_name'] ?? '',
                        (int)($row['trips_count'] ?? 1),
                        // (float)($row['batch_size'] ?? 0),
                        (float)($row['quantity'] ?? 0),
                        // (float)($row['truck_empty'] ?? 0),
                        // (float)($row['loaded_weight'] ?? 0),
                        // (float)($row['netweight'] ?? 0),
                        // (float)($row['amount_untaxed'] ?? 0),
                        // (float)($row['amount_tax'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Total Customer Volume',
                    (int)($data['total_trips'] ?? 0),
                    // (float)($data['total_batch_size'] ?? 0),
                    (float)($data['total_quantity'] ?? 0),
                    // (float)($data['total_truck_empty'] ?? 0),
                    // (float)($data['total_loaded_weight'] ?? 0),
                    // (float)($data['total_net_weight'] ?? 0),
                    // (float)($data['total_untaxed'] ?? 0),
                    // (float)($data['total_tax'] ?? 0),
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['batch_dispatches'])) {
                    $batchRows = [];
                    foreach ($data['batch_dispatches'] as $bi => $bRow) {
                        $batchRows[] = [
                            $bi + 1,
                            $bRow['dispatch_time'] ?? '',
                            $bRow['docket_no'] ?? $bRow['dispatch_no'] ?? '',
                            $bRow['customer_name'] ?? '',
                            $bRow['site_name'] ?? '',
                            $bRow['truck_no'] ?? '',
                            $bRow['driver_name'] ?? '',
                            $bRow['mix_name'] ?? '',
                            $bRow['concrete_grade'] ?? '',
                            (float)($bRow['batch_size'] ?? 0),
                            (float)($bRow['delivered_qty'] ?? 0),
                            (float)($bRow['empty_weight'] ?? 0),
                            (float)($bRow['loaded_weight'] ?? 0),
                            (float)($bRow['net_weight'] ?? 0),
                            (float)($bRow['rate'] ?? 0),
                            (float)($bRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'CUSTOMER BATCHING / TRIP VERIFICATION LIST',
                        'headers' => ['Trip #', 'Date & Time', 'Dispatch No', 'Customer Name', 'Unloading Site', 'Truck / Mixer', /*'Driver', 'Mix Design',*/ 'Grade', /*'Batch Size (m³)',*/ 'Delivered Qty (m³)', 'Empty Wt (T)', 'Loaded Wt (T)', 'Net Wt (T)', /*'Rate',*/ 'Total Amt'],
                        'rows' => $batchRows,

                    ];
                }
            } elseif ($type === 'truck_consolidated') {
                $title = "TRUCK WISE TRIP REPORT";
                $headersList = ['Trip #', 'Truck / Mixer', 'Date & Time', 'DSP No', 'Customer Name', 'Unloading Site', 'Grade', 'Delivered Qty (m³)', 'Empty Wt (T)', 'Loaded Wt (T)', 'Net Wt (T)', 'Taxable Amt', 'Tax Amt', 'Total Amt'];
                foreach (($data['truck_trips'] ?? $data['transactions'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['truck_no'] ?? '',
                        $row['dispatch_time'] ?? '',
                        $row['docket_no'] ?? '',
                        $row['customer_name'] ?? '',
                        $row['site_name'] ?? '',
                        $row['concrete_grade'] ?? '',
                        (float)($row['delivered_qty'] ?? 0),
                        (float)($row['empty_weight'] ?? 0),
                        (float)($row['loaded_weight'] ?? 0),
                        (float)($row['net_weight'] ?? 0),
                        (float)($row['amount_untaxed'] ?? 0),
                        (float)($row['amount_tax'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Total Fleet Volume', '', '', '', '', '',
                    (float)($data['total_quantity'] ?? 0),
                    (float)($data['total_truck_empty'] ?? 0),
                    (float)($data['total_loaded_weight'] ?? 0),
                    (float)($data['total_net_weight'] ?? 0),
                    (float)($data['total_untaxed'] ?? 0),
                    (float)($data['total_tax'] ?? 0),
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['truck_groups'])) {
                    $tgRows = [];
                    foreach ($data['truck_groups'] as $ti => $tRow) {
                        $tgRows[] = [
                            $ti + 1,
                            $tRow['truck_no'] ?? '',
                            (int)($tRow['trips_count'] ?? 1),
                            (float)($tRow['total_batch'] ?? 0),
                            (float)($tRow['total_qty'] ?? 0),
                            (float)($tRow['total_amount'] ?? 0),
                        ];
                    }
                    $tgCount = count($data['truck_groups']);
                    $extraSections['tables'][] = [
                        'title' => 'TRUCK FLEET CONSOLIDATED SUMMARY',
                        'headers' => ['#', 'Truck / Mixer Reg', 'Trips', 'Batch Size (m³)', 'Delivered Qty (m³)', 'Total Amt'],
                        'rows' => $tgRows,
                        'totalRow' => [
                            '', "Grand Fleet Total ({$tgCount} Trucks)",
                            (int)($data['total_trips'] ?? collect($data['truck_groups'])->sum('trips_count')),
                            (float)($data['total_batch_size'] ?? collect($data['truck_groups'])->sum('total_batch')),
                            (float)($data['total_quantity'] ?? collect($data['truck_groups'])->sum('total_qty')),
                            (float)($data['total_amount'] ?? collect($data['truck_groups'])->sum('total_amount')),
                        ],
                    ];
                }
            } elseif ($type === 'site_consolidated') {
                $title = "UNLOAD SITE CONSOLIDATED REPORT";
                $headersList = ['#', 'Unload Site Name', 'Customer / Party', 'Trips', 'Batch Size (m³)', 'Delivered Qty (m³)', 'Total Amt'];
                foreach (($data['transactions'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['site_name'] ?? '',
                        $row['customer_name'] ?? '-',
                        (int)($row['trips_count'] ?? 1),
                        (float)($row['batch_size'] ?? 0),
                        (float)($row['quantity'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Total Site Volume', '',
                    (int)($data['total_trips'] ?? 0),
                    (float)($data['total_batch_size'] ?? 0),
                    (float)($data['total_quantity'] ?? 0),
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['batch_dispatches'])) {
                    $siteTripRows = [];
                    foreach ($data['batch_dispatches'] as $si => $sRow) {
                        $siteTripRows[] = [
                            $si + 1,
                            $sRow['dispatch_time'] ?? '',
                            $sRow['docket_no'] ?? $sRow['dispatch_no'] ?? '',
                            $sRow['site_name'] ?? '',
                            $sRow['customer_name'] ?? '',
                            $sRow['truck_no'] ?? '',
                            $sRow['concrete_grade'] ?? $sRow['mix_name'] ?? '',
                            (float)($sRow['batch_size'] ?? 0),
                            (float)($sRow['delivered_qty'] ?? 0),
                            (float)($sRow['amount_total'] ?? 0),
                        ];
                    }
                    // $extraSections['tables'][] = [
                    //     'title' => 'UNLOAD SITE BATCHING / TRIP VERIFICATION LIST',
                    //     'headers' => ['Trip #', 'Date & Time', 'Dispatch No', 'Unload Site', 'Customer Name', 'Truck / Mixer', 'Mix Design / Grade', 'Batch Size (m³)', 'Delivered Qty (m³)', 'Total Amt'],
                    //     'rows' => $siteTripRows,
                    // ];
                }
            } elseif ($type === 'payment_mode_consolidated') {
                $title = "PAYMENT MODE CONSOLIDATED REPORT";
                $headersList = ['#', 'Payment Mode', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty (m³)', 'Total Amt'];
                foreach (($data['transactions'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['payment_mode'] ?? '',
                        (int)($row['trips_count'] ?? 1),
                        // (float)($row['batch_size'] ?? 0),
                        (float)($row['quantity'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Total Payment Modes',
                    (int)($data['total_trips'] ?? 0),
                    // (float)($data['total_batch_size'] ?? 0),
                    (float)($data['total_quantity'] ?? 0),
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['batch_dispatches'])) {
                    $pmTripRows = [];
                    foreach ($data['batch_dispatches'] as $pi => $pRow) {
                        $pmTripRows[] = [
                            $pi + 1,
                            $pRow['dispatch_time'] ?? '',
                            $pRow['docket_no'] ?? $pRow['dispatch_no'] ?? '',
                            $pRow['payment_mode'] ?? '',
                            $pRow['customer_name'] ?? '',
                            $pRow['site_name'] ?? '',
                            $pRow['truck_no'] ?? '',
                            $pRow['concrete_grade'] ?? $pRow['mix_name'] ?? '',
                            (float)($pRow['delivered_qty'] ?? 0),
                            (float)($pRow['amount_total'] ?? 0),
                        ];
                    }
                    // $extraSections['tables'][] = [
                    //     'title' => 'PAYMENT MODE BATCHING / TRIP VERIFICATION LIST',
                    //     'headers' => ['Trip #', 'Date & Time', 'Dispatch No', 'Payment Mode', 'Customer Name', 'Unload Site', 'Truck / Mixer', 'Mix Design / Grade', 'Delivered Qty (m³)', 'Total Amt'],
                    //     'rows' => $pmTripRows,
                    // ];
                }
            } elseif ($type === 'sales') {
                $title = "Sales Dispatch & Invoice wise Breakdown
";
                $headersList = [
                    '#',
                    'Date',
                    'Dispatch / Batch',
                    'Invoice Details',
                    'Customer / Site',
                    'Qty (m³)',
                    'Empty Wt (T)',
                    'Loaded Wt (T)',
                    'Net Wt (T)',
                    'Taxable Amt',
                    'Tax Amt',
                    'Total Amt'
                ];

                foreach (($data['transactions'] ?? []) as $i => $row) {
                    $dispatchBatch = trim(($row['dispatch_no'] ?? '-') . (!empty($row['batch_no']) && $row['batch_no'] !== '-' ? ' / ' . $row['batch_no'] : ''));
                    $invoiceDetails = (!empty($row['invoice_number']) && $row['invoice_number'] !== '-')
                        ? ($row['invoice_number'] . (!empty($row['invoice_date']) && $row['invoice_date'] !== '-' ? ' (' . $row['invoice_date'] . ')' : ''))
                        : 'Unbilled';
                    $customerSite = trim(($row['customer_name'] ?? '') . (!empty($row['site_name']) && $row['site_name'] !== 'N/A' ? ' / ' . $row['site_name'] : ''));
                    $dateStr = !empty($row['date']) ? (str_contains($row['date'], '-') ? \Carbon\Carbon::parse($row['date'])->format('d-m-Y') : $row['date']) : '';

                    $rows[] = [
                        $i + 1,
                        $dateStr,
                        $dispatchBatch,
                        $invoiceDetails,
                        $customerSite,
                        (float)($row['quantity'] ?? 0),
                        (float)($row['truck_empty'] ?? $row['empty_weight'] ?? 0),
                        (float)($row['loaded_weight'] ?? $row['truck_loaded'] ?? 0),
                        (float)($row['netweight'] ?? $row['net_weight'] ?? 0),
                        (float)($row['amount_untaxed'] ?? 0),
                        (float)($row['amount_tax'] ?? 0),
                        (float)($row['amount_total'] ?? 0),
                    ];
                }

                $totalRow = [
                    '', 'Total Sales', '', '', '',
                    (float)($data['total_quantity'] ?? collect($data['transactions'] ?? [])->sum('quantity')),
                    (float)($data['total_truck_empty'] ?? collect($data['transactions'] ?? [])->sum(fn($r) => $r['truck_empty'] ?? $r['empty_weight'] ?? 0)),
                    (float)($data['total_loaded_weight'] ?? collect($data['transactions'] ?? [])->sum(fn($r) => $r['loaded_weight'] ?? $r['truck_loaded'] ?? 0)),
                    (float)($data['total_net_weight'] ?? collect($data['transactions'] ?? [])->sum(fn($r) => $r['netweight'] ?? $r['net_weight'] ?? 0)),
                    (float)($data['total_untaxed'] ?? 0),
                    (float)($data['total_tax'] ?? 0),
                    (float)($data['total_amount'] ?? 0),
                ];

                if (!empty($data['product_summary'])) {
                    $prodRows = [];
                    foreach ($data['product_summary'] as $pi => $pRow) {
                        $prodRows[] = [
                            $pi + 1,
                            $pRow['mix_name'] ?? '',
                            $pRow['concrete_grade'] ?? '',
                            $pRow['uom'] ?? 'm³',
                            (int)($pRow['trips_count'] ?? 1),
                            // (float)($pRow['batch_size'] ?? 0),
                            (float)($pRow['quantity'] ?? 0),
                            // (float)($pRow['truck_empty'] ?? 0),
                            // (float)($pRow['loaded_weight'] ?? 0),
                            // (float)($pRow['netweight'] ?? 0),
                            // (float)($pRow['avg_rate'] ?? 0),
                            // (float)($pRow['amount_untaxed'] ?? 0),
                            // (float)($pRow['amount_tax'] ?? 0),
                            (float)($pRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'PRODUCT CONSOLIDATED SUMMARY (MIX DESIGN & GRADE WISE)',
                        'headers' => ['#', 'Mix Design Name', 'Grade', 'UOM', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty', /* 'Empty Wt (T)', 'Loaded Wt (T)', 'Net Wt (T)', 'Avg Rate', 'Taxable Amt', 'Tax Amt', */ 'Total Amt'],
                        'rows' => $prodRows,
                    ];
                }

                if (!empty($data['customer_summary']) || !empty($data['party_summary'])) {
                    $custRows = [];
                    $custSource = !empty($data['customer_summary']) ? $data['customer_summary'] : $data['party_summary'];
                    foreach ($custSource as $ci => $cRow) {
                        $custRows[] = [
                            $ci + 1,
                            $cRow['party_name'] ?? $cRow['customer_name'] ?? '',
                            (int)($cRow['trips_count'] ?? 1),
                            // (float)($cRow['batch_size'] ?? 0),
                            (float)($cRow['quantity'] ?? 0),
                            // (float)($cRow['truck_empty'] ?? 0),
                            // (float)($cRow['loaded_weight'] ?? 0),
                            // (float)($cRow['netweight'] ?? 0),
                            // (float)($cRow['amount_untaxed'] ?? 0),
                            // (float)($cRow['amount_tax'] ?? 0),
                            (float)($cRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'CUSTOMER CONSOLIDATED SUMMARY (PARTY WISE)',
                        'headers' => ['#', 'Customer / Party Name', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty', /* 'Empty Wt (T)', 'Loaded Wt (T)', 'Net Wt (T)', 'Taxable Amt', 'Tax Amt', */ 'Total Amt'],
                        'rows' => $custRows,
                    ];
                }

                if (!empty($data['truck_summary'])) {
                    $truckRows = [];
                    foreach ($data['truck_summary'] as $ti => $tRow) {
                        $truckRows[] = [
                            $ti + 1,
                            $tRow['truck_no'] ?? '',
                            (int)($tRow['trips_count'] ?? 1),
                            // (float)($tRow['batch_size'] ?? 0),
                            (float)($tRow['quantity'] ?? 0),
                            (float)($tRow['truck_empty'] ?? 0),
                            (float)($tRow['loaded_weight'] ?? 0),
                            (float)($tRow['netweight'] ?? 0),
                            // (float)($tRow['amount_untaxed'] ?? 0),
                            // (float)($tRow['amount_tax'] ?? 0),
                            (float)($tRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'TRUCK CONSOLIDATED SUMMARY (FLEET WISE)',
                        'headers' => ['#', 'Truck / Vehicle Registration', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty', 'Empty Wt (T)', 'Loaded Wt (T)', 'Net Wt (T)', /* 'Taxable Amt', 'Tax Amt', */ 'Total Amt'],
                        'rows' => $truckRows,
                    ];
                }

                if (!empty($data['site_summary'])) {
                    $siteRows = [];
                    foreach ($data['site_summary'] as $si => $sRow) {
                        $siteRows[] = [
                            $si + 1,
                            $sRow['site_name'] ?? '',
                            $sRow['customer_name'] ?? '-',
                            (int)($sRow['trips_count'] ?? 1),
                            (float)($sRow['batch_size'] ?? 0),
                            (float)($sRow['quantity'] ?? 0),
                            (float)($sRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'UNLOAD SITE CONSOLIDATED SUMMARY',
                        'headers' => ['#', 'Unload Site Name', 'Customer / Party', 'Trips', 'Batch Size (m³)', 'Delivered Qty (m³)', 'Total Amt'],
                        'rows' => $siteRows,
                    ];
                }

                if (!empty($data['payment_mode_summary'])) {
                    $pmRows = [];
                    foreach ($data['payment_mode_summary'] as $pi => $pRow) {
                        $pmRows[] = [
                            $pi + 1,
                            $pRow['payment_mode'] ?? '',
                            (int)($pRow['trips_count'] ?? 1),
                            // (float)($pRow['batch_size'] ?? 0),
                            (float)($pRow['quantity'] ?? 0),
                            (float)($pRow['amount_total'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'PAYMENT MODE CONSOLIDATED SUMMARY',
                        'headers' => ['#', 'Payment Mode', 'Trips', /* 'Batch Size (m³)', */ 'Delivered Qty (m³)', 'Total Amt'],
                        'rows' => $pmRows,
                    ];
                }
            } elseif ($type === 'sales_executive') {
                $title = "SALES EXECUTIVE CONSOLIDATED REPORT";
                $headersList = ['#', 'Sales Executive Name', 'Code', 'Trips', 'Batch Size (m³)', 'Delivered Qty (m³)'];
                foreach (($data['consolidated'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['sales_executive_name'] ?? '',
                        $row['executive_code'] ?? '',
                        (int)($row['trips_count'] ?? 1),
                        (float)($row['batch_size'] ?? 0),
                        (float)($row['quantity'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Grand Total', '',
                    (int)($data['totals']['trips_count'] ?? 0),
                    (float)($data['totals']['batch_size'] ?? 0),
                    (float)($data['totals']['quantity'] ?? 0),
                ];

                if (!empty($data['executive_customer_summary'])) {
                    $ecRows = [];
                    foreach ($data['executive_customer_summary'] as $ei => $eRow) {
                        $ecRows[] = [
                            $ei + 1,
                            $eRow['sales_executive_name'] ?? '',
                            $eRow['customer_name'] ?? '',
                            (int)($eRow['trips_count'] ?? 1),
                            (float)($eRow['quantity'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'EXECUTIVE & CUSTOMER VOLUME BREAKDOWN',
                        'headers' => ['#', 'Sales Executive', 'Customer / Account', 'Trips', 'Delivered Qty (m³)'],
                        'rows' => $ecRows,
                    ];
                }

                if (!empty($data['transactions'])) {
                    $execTripRows = [];
                    foreach ($data['transactions'] as $ti => $tRow) {
                        $execTripRows[] = [
                            $ti + 1,
                            $tRow['datetime'] ?? $tRow['date'] ?? '',
                            $tRow['dispatch_no'] ?? '',
                            $tRow['sales_executive_name'] ?? '',
                            $tRow['customer_name'] ?? '',
                            $tRow['site_name'] ?? '',
                            $tRow['truck_no'] ?? '',
                            $tRow['concrete_grade'] ?? $tRow['mix_name'] ?? '',
                            (float)($tRow['batch_size'] ?? 0),
                            (float)($tRow['quantity'] ?? $tRow['delivered_qty'] ?? 0),
                        ];
                    }
                    // $extraSections['tables'][] = [
                    //     'title' => 'SALES EXECUTIVE BATCHING / TRIP VERIFICATION LIST',
                    //     'headers' => ['Trip #', 'Date & Time', 'Dispatch / DSP No', 'Sales Executive', 'Customer Name', 'Unload Site', 'Truck / Mixer', 'Mix Design / Grade', 'Batch Size (m³)', 'Delivered Qty (m³)'],
                    //     'rows' => $execTripRows,
                    // ];
                }
            } elseif ($type === 'driver') {
                $title = "DRIVER DISPATCH CONSOLIDATED REPORT";
                $headersList = ['#', 'Driver Name', 'Code', 'Trips', 'Batch Size (m³)', 'Delivered Qty (m³)'];
                foreach (($data['consolidated'] ?? $data['items'] ?? []) as $i => $row) {
                    $rows[] = [
                        $i + 1,
                        $row['driver_name'] ?? '',
                        $row['driver_code'] ?? '',
                        (int)($row['trips_count'] ?? 1),
                        (float)($row['batch_size'] ?? 0),
                        (float)($row['quantity'] ?? 0),
                    ];
                }
                $totalRow = [
                    '', 'Grand Total', '',
                    (int)($data['totals']['trips_count'] ?? 0),
                    (float)($data['totals']['batch_size'] ?? 0),
                    (float)($data['totals']['quantity'] ?? 0),
                ];

                if (!empty($data['driver_vehicle_summary'])) {
                    $dvRows = [];
                    foreach ($data['driver_vehicle_summary'] as $di => $dRow) {
                        $dvRows[] = [
                            $di + 1,
                            $dRow['driver_name'] ?? '',
                            $dRow['truck_no'] ?? '',
                            (int)($dRow['trips_count'] ?? 1),
                            (float)($dRow['quantity'] ?? 0),
                        ];
                    }
                    $extraSections['tables'][] = [
                        'title' => 'DRIVER & VEHICLE TRIPS BREAKDOWN',
                        'headers' => ['#', 'Driver Name', 'Vehicle / Truck Reg', 'Trips', 'Delivered Qty (m³)'],
                        'rows' => $dvRows,
                    ];
                }

                if (!empty($data['transactions'])) {
                    $drvTripRows = [];
                    foreach ($data['transactions'] as $ti => $tRow) {
                        $drvTripRows[] = [
                            $ti + 1,
                            $tRow['datetime'] ?? $tRow['date'] ?? '',
                            $tRow['dispatch_no'] ?? '',
                            $tRow['driver_name'] ?? '',
                            $tRow['truck_no'] ?? '',
                            $tRow['customer_name'] ?? '',
                            $tRow['site_name'] ?? '',
                            $tRow['concrete_grade'] ?? $tRow['mix_name'] ?? '',
                            (float)($tRow['batch_size'] ?? 0),
                            (float)($tRow['quantity'] ?? $tRow['delivered_qty'] ?? 0),
                        ];
                    }
                    // $extraSections['tables'][] = [
                    //     'title' => 'DRIVER BATCHING / TRIP VERIFICATION LIST',
                    //     'headers' => ['Trip #', 'Date & Time', 'Dispatch / DSP No', 'Driver Name', 'Truck / Mixer', 'Customer Name', 'Unload Site', 'Mix Design / Grade', 'Batch Size (m³)', 'Delivered Qty (m³)'],
                    //     'rows' => $drvTripRows,
                    // ];
                }
            } elseif (in_array($type, ['ledger', 'patron', 'payment', 'receipt'])) {
                $titles = [
                    'ledger'  => 'GENERAL LEDGER STATEMENT',
                    'patron'  => 'PATRON STATEMENT OF ACCOUNTS',
                    'payment' => 'PAYMENT LOG STATEMENT',
                    'receipt' => 'RECEIPT LOG STATEMENT',
                ];
                $title = $titles[$type] ?? 'STATEMENT OF ACCOUNTS';
                $headersList = ['Date', 'Particulars', 'Reference', 'Amount', 'Type', 'Balance'];

                $balance = (float)($data['opening_balance'] ?? 0);
                $opDate = $start ? \Carbon\Carbon::parse($start)->format('d-m-Y') : '';

                // Opening Balance Row
                $rows[] = [
                    $opDate,
                    'OPENING BALANCE',
                    '---',
                    abs($balance),
                    $balance >= 0 ? 'DR' : 'CR',
                    abs($balance)
                ];

                foreach (($data['transactions'] ?? []) as $row) {
                    $debit  = (float)($row['debit'] ?? 0);
                    $credit = (float)($row['credit'] ?? 0);
                    $balance += ($debit - $credit);
                    $amt = (float)($row['amount'] ?? ($debit > 0 ? $debit : $credit));
                    $typeStr = strtoupper($row['type'] ?? ($debit > 0 ? 'Dr' : 'Cr'));
                    $rowDate = !empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d-m-Y') : '';

                    $rows[] = [
                        $rowDate,
                        $row['narration'] ?? '',
                        $row['voucher_no'] ?? '',
                        $amt,
                        $typeStr,
                        abs($balance)
                    ];
                }

                $totalRow = [
                    '', 'NET CLOSING BALANCE', '',
                    abs($balance),
                    $balance >= 0 ? 'DEBIT' : 'CREDIT',
                    abs($balance)
                ];

                if ($type === 'patron' && (!empty($data['invoiced_tax']) || !empty($data['invoiced_nontax']) || !empty($data['purchased']) || !empty($data['amount_received']) || !empty($data['amount_paid']))) {
                    $extraSections['tables'][] = [
                        'title' => 'PATRON ACCOUNT SUMMARY OVERVIEW',
                        'headers' => ['Account Summary Metric', 'Amount (₹)'],
                        'rows' => [
                            ['Opening Balance', (float)($data['opening_balance'] ?? 0)],
                            ['Invoiced (Taxable)', (float)($data['invoiced_tax'] ?? 0)],
                            ['Invoiced (Non-Tax)', (float)($data['invoiced_nontax'] ?? 0)],
                            ['Total Invoiced Amount', (float)(($data['invoiced_tax'] ?? 0) + ($data['invoiced_nontax'] ?? 0))],
                            ['Sales Discount', (float)($data['sales_discount'] ?? 0)],
                            ['Total Purchased', (float)($data['purchased'] ?? 0)],
                            ['Amount Received', (float)($data['amount_received'] ?? 0)],
                            ['Amount Paid', (float)($data['amount_paid'] ?? 0)],
                            ['Net Balance Due', abs($balance)],
                        ]
                    ];
                }
            } else {
                $headersList = ['Date', 'Particulars', 'Voucher Type', 'Voucher No', 'Amount', 'Type', 'Balance'];
                $balance = $data['opening_balance'] ?? 0;
                if ($balance != 0) {
                    $rows[] = [$start, 'Opening Balance', '', '', abs($balance), $balance > 0 ? 'Dr' : 'Cr', $balance];
                }
                foreach (($data['transactions'] ?? []) as $row) {
                    $balance += (($row['debit'] ?? 0) - ($row['credit'] ?? 0));
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['narration'] ?? '',
                        $row['voucher_type'] ?? '',
                        $row['voucher_no'] ?? '',
                        $row['amount'] ?? 0,
                        $row['type'] ?? '',
                        $balance
                    ];
                }
            }
        }

        return $this->export($title, $period, $headersList, $rows, $totalRow, $extraSections);
    }

    /**
     * Determines the appropriate Excel number formatting code based on column header name.
     */
    private function getColumnFormatCode(?string $header): ?string
    {
        if (empty($header)) {
            return null;
        }

        $h = strtolower(trim($header));
        if ($h === '#' || $h === 's.no' || $h === 'sl no' || $h === 'sr no') {
            return null;
        }

        // Exclusions: tax name, tax rate %, statuses, booleans, dates, shifts, IDs, references
        if (
            str_contains($h, 'tax name') ||
            str_contains($h, 'tax rate') ||
            str_contains($h, 'is tax') ||
            str_contains($h, 'status') ||
            str_contains($h, 'shift') ||
            str_contains($h, 'date') ||
            str_contains($h, 'number') ||
            str_contains($h, 'reg') ||
            str_contains($h, 'name') ||
            str_contains($h, 'mobile') ||
            str_contains($h, 'irn') ||
            str_contains($h, 'gstin') ||
            str_contains($h, 'uom') ||
            str_contains($h, 'grade')
        ) {
            return null;
        }

        // Money format: "₹" #,##0.00;("₹" #,##0.00);"₹" 0.00
        $moneyKeywords = [
            'amount', 'amt', 'rate', 'charge', 'price', 'discount', 
            'adjustment', 'round off', 'pass', 'cogs', 'cost', 
            'revenue', 'spend', 'profit', 'balance', 'value', 'taxable',
            'cgst', 'sgst', 'igst', 'utgst', 'net amount'
        ];
        foreach ($moneyKeywords as $kw) {
            if (str_contains($h, $kw)) {
                return '"₹" #,##0.00;("₹" #,##0.00);"₹" 0.00';
            }
        }
        if (str_contains($h, 'tax') && !str_contains($h, 'name') && !str_contains($h, 'rate') && !str_contains($h, 'inclusive')) {
            return '"₹" #,##0.00;("₹" #,##0.00);"₹" 0.00';
        }

        // Weight / Decimal quantity format: #,##0.00 (2 decimal places)
        $weightKeywords = ['weight', 'wt', 'batch size', 'quantity', 'qty', 'delivered', 'capacity', 'trips'];
        foreach ($weightKeywords as $kw) {
            if (str_contains($h, $kw)) {
                // If strictly trips or count, format as integer
                if (str_contains($h, 'trips') || str_contains($h, 'count')) {
                    return '#,##0';
                }
                return '#,##0.00';
            }
        }

        return null;
    }
}

