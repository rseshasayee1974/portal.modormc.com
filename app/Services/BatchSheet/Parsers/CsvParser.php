<?php

namespace App\Services\BatchSheet\Parsers;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use App\Models\BatchSheetFieldDictionary;
use Illuminate\Support\Facades\Log;

class CsvParser implements DocumentParser
{
    public function canHandle(string $mimeType, string $extension): bool
    {
        return $mimeType === 'text/csv' || $mimeType === 'text/plain' || $extension === 'csv';
    }

    public function getParserName(): string
    {
        return 'CSV Delimited Parser';
    }

    public function parse(string $filePath, array $options = []): ParsedDocument
    {
        Log::info("CsvParser: Parsing file {$filePath}");

        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new \RuntimeException("Failed to open CSV file at path: {$filePath}");
        }

        $rawText = "";
        $headerFields = [];
        $materialRows = [];
        $rows = [];

        // Read all rows
        while (($row = fgetcsv($file)) !== false) {
            $rows[] = array_map('trim', $row);
            $rawText .= implode("\t", $row) . "\n";
        }
        fclose($file);

        $rowCount = count($rows);

        // 1. Scan cells for headers
        for ($r = 0; $r < $rowCount; $r++) {
            $cols = count($rows[$r]);
            for ($c = 0; $c < $cols; $c++) {
                $val = $rows[$r][$c];
                if (empty($val)) continue;

                $dictEntry = BatchSheetFieldDictionary::resolveCanonical($val, 'header');
                if ($dictEntry) {
                    // Extract value from right cell or down cell
                    $rightVal = isset($rows[$r][$c + 1]) ? $rows[$r][$c + 1] : '';
                    $downVal = isset($rows[$r + 1][$c]) ? $rows[$r + 1][$c] : '';
                    $headerVal = !empty($rightVal) ? $rightVal : $downVal;

                    if (!empty($headerVal)) {
                        $headerFields[$dictEntry->canonical_name] = $headerVal;
                    }
                }
            }
        }

        // 2. Scan rows for materials
        for ($r = 0; $r < $rowCount; $r++) {
            $cols = count($rows[$r]);
            for ($c = 0; $c < $cols; $c++) {
                $val = $rows[$r][$c];
                if (empty($val) || is_numeric($val)) continue;

                $dictEntry = BatchSheetFieldDictionary::resolveCanonical($val, 'material');
                if ($dictEntry) {
                    // Check other cells in this row for numeric values
                    $numbers = [];
                    for ($colIdx = $c + 1; $colIdx < $cols; $colIdx++) {
                        $v = str_replace(',', '', $rows[$r][$colIdx] ?? '');
                        if (is_numeric($v)) {
                            $numbers[] = (float)$v;
                        }
                    }

                    if (!empty($numbers)) {
                        $target = count($numbers) >= 2 ? $numbers[0] : 0;
                        $actual = count($numbers) >= 2 ? $numbers[1] : $numbers[0];
                        $deviation = count($numbers) >= 3 ? $numbers[2] : ($actual - $target);

                        $materialRows[] = [
                            'material_name' => $val,
                            'target_qty' => $target,
                            'actual_qty' => $actual,
                            'deviation_quantity' => $deviation,
                        ];
                    }
                    break; // Skip to next row once we found a material on this row
                }
            }
        }

        // Fallback: structured table parsing if no dictionary match
        if (empty($materialRows)) {
            $materialRows = $this->parseStructuredTable($rows);
        }

        return new ParsedDocument([
            'rawText' => $rawText,
            'headerFields' => $headerFields,
            'materialRows' => $materialRows,
            'confidence' => 99.0, // High confidence for structural CSV
            'parserUsed' => $this->getParserName(),
        ]);
    }

    protected function parseStructuredTable(array $rows): array
    {
        $materials = [];
        $rowCount = count($rows);

        for ($r = 0; $r < $rowCount; $r++) {
            $cols = count($rows[$r]);
            for ($c = 0; $c < $cols; $c++) {
                $val = strtolower($rows[$r][$c]);
                if (in_array($val, ['material', 'material name', 'item', 'description', 'product'], true)) {
                    for ($rowIdx = $r + 1; $rowIdx < $rowCount; $rowIdx++) {
                        $matName = $rows[$rowIdx][$c] ?? '';
                        if (empty($matName) || in_array(strtolower($matName), ['total', 'totals', 'summary'], true)) {
                            break;
                        }

                        $target = (float)str_replace(',', '', $rows[$rowIdx][$c + 1] ?? '0');
                        $actual = (float)str_replace(',', '', $rows[$rowIdx][$c + 2] ?? '0');
                        $deviation = (float)str_replace(',', '', $rows[$rowIdx][$c + 3] ?? '0');

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
