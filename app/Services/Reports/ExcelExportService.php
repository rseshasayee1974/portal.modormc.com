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

        // 4. Custom Structural Tables (e.g. GSTR-3B Table 3.1 & Table 4)
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
                $currentRow += 2; // Spacing between tables
            }
        }

        // 5. Standard Table Headers & Rows
        if (empty($extraSections['tables'])) {
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

            // 6. Optional Total Row
            if (!empty($totalRow)) {
                $sheet->fromArray($totalRow, null, "A{$currentRow}");
                $sheet->getStyle("A{$currentRow}:{$maxColLetter}{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getRowDimension($currentRow)->setRowHeight(22);
                $currentRow++;
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
    public function generateExcelReport(string $type, string $start, string $end, array $data): Spreadsheet
    {
        $title = strtoupper(str_replace('_', ' ', $type)) . " REPORT";
        $period = "Period: $start to $end";
        
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
                $headersList = ['Date', 'Product Name', 'UOM', 'Opening Qty', 'Current Stock', 'Status'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['product_name'] ?? '',
                        $row['uom'] ?? '',
                        $row['opening_qty'] ?? 0,
                        $row['quantity'] ?? 0,
                        $row['status'] ?? ''
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
                $headersList = ['Start Date', 'Batch No', 'Work Order', 'Mix Design', 'Batch Size (m³)', 'Operator', 'Status'];
                foreach (($data['transactions'] ?? []) as $row) {
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['batch_no'] ?? '',
                        $row['work_order'] ?? '',
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
}
