<?php

namespace App\Services\BatchSheet\Parsers;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use App\Models\BatchSheetFieldDictionary;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class ExcelParser implements DocumentParser
{
    public function canHandle(string $mimeType, string $extension): bool
    {
        $excelMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];
        return in_array($mimeType, $excelMimes, true) || in_array($extension, ['xlsx', 'xls'], true);
    }

    public function getParserName(): string
    {
        return 'Excel Workbook Parser';
    }

    public function parse(string $filePath, array $options = []): ParsedDocument
    {
        Log::info("ExcelParser: Loading file {$filePath}");

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = min($sheet->getHighestRow(), 150); // limit to 150 rows
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn), 26); // limit to 26 columns

        $rawText = "";
        $headerFields = [];
        $materialRows = [];

        // 1. Scan cells to extract header fields and build raw text
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowValues = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCell([$col, $row]);
                $val = trim((string)$cell->getValue());
                $rowValues[] = $val;

                if (empty($val)) {
                    continue;
                }

                // Check if this cell is a known header alias from our dictionary
                $dictEntry = BatchSheetFieldDictionary::resolveCanonical($val, 'header');
                if ($dictEntry) {
                    // Look for the value in the adjacent cell (right cell or down cell)
                    $rightCellVal = trim((string)$sheet->getCell([$col + 1, $row])->getValue());
                    $downCellVal = trim((string)$sheet->getCell([$col, $row + 1])->getValue());

                    $headerVal = !empty($rightCellVal) ? $rightCellVal : $downCellVal;
                    if (!empty($headerVal)) {
                        $headerFields[$dictEntry->canonical_name] = $headerVal;
                    }
                }
            }
            $rawText .= implode("\t", $rowValues) . "\n";
        }

        // 2. Scan rows to identify materials
        // We look for rows containing a material-like name, along with target/actual quantities
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cellVal = trim((string)$sheet->getCell([$col, $row])->getValue());
                if (empty($cellVal) || is_numeric($cellVal)) {
                    continue;
                }

                // Try to resolve as material alias
                $dictEntry = BatchSheetFieldDictionary::resolveCanonical($cellVal, 'material');
                if ($dictEntry) {
                    // Scan the rest of this row for numbers
                    $numbers = [];
                    for ($c = $col + 1; $c <= $highestColumnIndex; $c++) {
                        $v = trim((string)$sheet->getCell([$c, $row])->getValue());
                        if (is_numeric(str_replace(',', '', $v))) {
                            $numbers[] = (float)str_replace(',', '', $v);
                        }
                    }

                    if (!empty($numbers)) {
                        $target = count($numbers) >= 2 ? $numbers[0] : 0;
                        $actual = count($numbers) >= 2 ? $numbers[1] : $numbers[0];
                        $deviation = count($numbers) >= 3 ? $numbers[2] : ($actual - $target);

                        $materialRows[] = [
                            'material_name' => $cellVal,
                            'target_qty' => $target,
                            'actual_qty' => $actual,
                            'deviation_quantity' => $deviation,
                        ];
                    }
                    break; // Skip to next row once we found a material on this row
                }
            }
        }

        // Fallback: If we couldn't find materials via dictionary, let's look for standard headers
        if (empty($materialRows)) {
            $materialRows = $this->parseStructuredTable($sheet, $highestRow, $highestColumnIndex);
        }

        return new ParsedDocument([
            'rawText' => $rawText,
            'headerFields' => $headerFields,
            'materialRows' => $materialRows,
            'confidence' => 98.0,
            'parserUsed' => $this->getParserName(),
        ]);
    }

    /**
     * Fallback table parser for columnar Excel sheets
     */
    protected function parseStructuredTable($sheet, int $highestRow, int $highestColumnIndex): array
    {
        $materials = [];
        // Scan rows for keywords like "Material", "Item", "Product"
        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $val = strtolower(trim((string)$sheet->getCell([$col, $row])->getValue()));
                if (in_array($val, ['material', 'material name', 'item', 'description', 'product'], true)) {
                    // Let's assume columns are: Col => Material Name, Col+1 => Target, Col+2 => Actual
                    // Walk down rows from here
                    for ($r = $row + 1; $r <= $highestRow; $r++) {
                        $matName = trim((string)$sheet->getCell([$col, $r])->getValue());
                        if (empty($matName) || in_array(strtolower($matName), ['total', 'totals', 'summary'], true)) {
                            break; // end of table
                        }

                        $target = (float)str_replace(',', '', (string)$sheet->getCell([$col + 1, $r])->getValue());
                        $actual = (float)str_replace(',', '', (string)$sheet->getCell([$col + 2, $r])->getValue());
                        $deviation = (float)str_replace(',', '', (string)$sheet->getCell([$col + 3, $r])->getValue());

                        if ($actual > 0) {
                            $materials[] = [
                                'material_name' => $matName,
                                'target_qty' => $target,
                                'actual_qty' => $actual,
                                'deviation_quantity' => $deviation ?: ($actual - $target),
                            ];
                        }
                    }
                    return $materials;
                }
            }
        }
        return [];
    }
}
