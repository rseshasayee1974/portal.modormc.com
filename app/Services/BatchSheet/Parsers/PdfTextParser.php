<?php

namespace App\Services\BatchSheet\Parsers;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as SmalotPdfParser;

class PdfTextParser implements DocumentParser
{
    public function canHandle(string $mimeType, string $extension): bool
    {
        return $mimeType === 'application/pdf' || $extension === 'pdf';
    }

    public function getParserName(): string
    {
        return 'PDF Text Parser';
    }

    public function parse(string $filePath, array $options = []): ParsedDocument
    {
        Log::info("PdfTextParser: Parsing file {$filePath}");

        $pdfParser = new SmalotPdfParser();
        $pdf = $pdfParser->parseFile($filePath);
        $text = $pdf->getText();

        Log::info("PdfTextParser: Extracted text length: " . strlen($text));

        // Extract header key-value fields from text
        $headerFields = $this->extractHeaderFields($text);
        
        // Extract batch number
        $batchNo = $this->extractBatchNo($text);
        if ($batchNo) {
            $headerFields['batch_number'] = $batchNo;
        }

        // Extract materials using strategies
        $materialRows = $this->extractMaterials($text);

        return new ParsedDocument([
            'rawText' => $text,
            'headerFields' => $headerFields,
            'materialRows' => $materialRows,
            'confidence' => 95.0, // High confidence for text-extracted PDFs
            'parserUsed' => $this->getParserName(),
        ]);
    }

    /**
     * Extracts lines that look like key-value pairs (e.g. "Customer: XYZ", "Date = 2026-06-20")
     */
    protected function extractHeaderFields(string $text): array
    {
        $fields = [];
        $lines = preg_split('/\r?\n/', $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^([a-zA-Z0-9\s_\-\.]+)\s*[:=]\s*(.+)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $val = trim($matches[2]);
                if (strlen($key) > 2 && strlen($key) < 40 && strlen($val) > 0 && strlen($val) < 100) {
                    $fields[$key] = $val;
                }
            }
        }

        return $fields;
    }

    protected function extractBatchNo(string $text): ?string
    {
        if (preg_match('/(?:Batch|Docket|Delivery|Order)\s*(?:Number|No\.?|Num)?\s*[:=]?\s*([a-zA-Z0-9\-\_]+)/i', $text, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    protected function extractMaterials(string $text): array
    {
        // Strategy 1: Columnar layout (Schwing Stetter)
        $materials = $this->parseColumnar($text);
        if (!empty($materials)) return $materials;

        // Strategy 2: Row-per-material layout
        $materials = $this->parseRowPerMaterial($text);
        if (!empty($materials)) return $materials;

        // Strategy 3: Key-value format
        $materials = $this->parseKeyValueFormat($text);
        if (!empty($materials)) return $materials;

        return [];
    }

    private function parseColumnar(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $materials = [];
        $headerNames = [];

        foreach ($lines as $i => $rawLine) {
            $line = trim($rawLine);
            if ($line === '') continue;

            // Detect header row
            if (empty($headerNames) && !$this->isNumericLine($line)) {
                $tokens = preg_split('/\s{2,}/', $line);
                $tokens = array_values(array_filter(array_map('trim', $tokens)));
                $materialLike = array_filter($tokens, fn($t) => preg_match('/^[A-Z0-9][A-Z0-9\s\/\-]{0,15}$/', $t));

                if (count($materialLike) >= 3) {
                    $headerNames = array_values($materialLike);
                }
            }

            // Detect data row
            if (!empty($headerNames) && preg_match('/total\s+actual\s+weight/i', $line)) {
                $numbers = $this->extractNumbers($line);

                if (count($numbers) < count($headerNames)) {
                    $nextLine = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
                    $numbers = array_merge($numbers, $this->extractNumbers($nextLine));
                }

                foreach ($headerNames as $idx => $name) {
                    if (isset($numbers[$idx])) {
                        $materials[] = [
                            'material_name' => $name,
                            'target_qty' => 0, // Columnar usually only lists actuals on this row
                            'actual_qty' => $numbers[$idx],
                            'deviation_quantity' => 0,
                        ];
                    }
                }

                if (!empty($materials)) return $materials;
            }
        }

        return $materials;
    }

    private function parseRowPerMaterial(string $text): array
    {
        $materials = [];
        $lines = preg_split('/\r?\n/', $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $this->isNumericLine($line)) continue;

            if (preg_match('/^([A-Z][A-Z0-9\s\/\-]{1,18}?)\s{2,}([\d\s.,\-]+)$/', $line, $match)) {
                $name = trim($match[1]);
                $numbers = $this->extractNumbers($match[2]);

                if (count($numbers) >= 2) {
                    $target = $numbers[0];
                    $actual = $numbers[1];
                    $deviation = count($numbers) >= 3 ? $numbers[2] : ($actual - $target);
                    
                    if (strlen($name) >= 2) {
                        $materials[] = [
                            'material_name' => $name,
                            'target_qty' => $target,
                            'actual_qty' => $actual,
                            'deviation_quantity' => $deviation,
                        ];
                    }
                }
            }
        }

        return $materials;
    }

    private function parseKeyValueFormat(string $text): array
    {
        $materials = [];
        preg_match_all(
            '/([A-Z][A-Z0-9\s\/\-]{1,18}?)\s*[:=]\s*([\d,]+(?:\.\d{1,3})?)\s*(?:kg|KG)?/m',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $name = trim($m[1]);
            $actual = (float) str_replace(',', '', $m[2]);
            if (strlen($name) >= 2 && strlen($name) <= 22) {
                $materials[] = [
                    'material_name' => $name,
                    'target_qty' => 0,
                    'actual_qty' => $actual,
                    'deviation_quantity' => 0,
                ];
            }
        }

        return $materials;
    }

    private function extractNumbers(string $line): array
    {
        preg_match_all('/-?\d+(?:[.,]\d+)?/', $line, $matches);
        return array_map(fn($n) => (float) str_replace(',', '', $n), $matches[0]);
    }

    private function isNumericLine(string $line): bool
    {
        $stripped = preg_replace('/[\d\s.,\-+%\/()]/', '', $line);
        return strlen($stripped) <= 3;
    }
}
