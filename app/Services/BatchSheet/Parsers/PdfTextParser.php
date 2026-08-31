<?php

namespace App\Services\BatchSheet\Parsers;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\Drivers\PlantDriverRegistry;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as SmalotPdfParser;

class PdfTextParser implements DocumentParser
{
    protected PlantDriverRegistry $plantDriverRegistry;

    public function __construct(?PlantDriverRegistry $plantDriverRegistry = null)
    {
        $this->plantDriverRegistry = $plantDriverRegistry ?? new PlantDriverRegistry();
    }

    public function canHandle(string $mimeType, string $extension): bool
    {
        return $mimeType === 'application/pdf' || $extension === 'pdf';
    }

    public function getParserName(): string
    {
        return 'Dynamic Multi-Plant PDF Parser';
    }

    public function parse(string $filePath, array $options = []): ParsedDocument
    {
        Log::info("PdfTextParser: Parsing file {$filePath}");

        $pdfParser = new SmalotPdfParser();
        $pdf = $pdfParser->parseFile($filePath);
        $text = $pdf->getText();

        Log::info("PdfTextParser: Extracted text length: " . strlen($text));

        // 1. Check dynamic plant driver registry for matching plant file
        $driver = $this->plantDriverRegistry->resolve($text, $options);
        if ($driver) {
            Log::info("PdfTextParser: Routing to dedicated plant driver [{$driver->getDriverCode()}] {$driver->getDriverName()}");
            $driverData = $driver->parse($text, $options);
            return new ParsedDocument([
                'rawText' => $text,
                'headerFields' => $driverData['headerFields'] ?? [],
                'materialRows' => $driverData['materialRows'] ?? [],
                'confidence' => $driverData['confidence'] ?? 98.0,
                'parserUsed' => $driver->getDriverName(),
            ]);
        }

        // 2. Generic fallback extraction
        $headerFields = $this->extractHeaderFields($text);
        
        $batchNo = $this->extractBatchNo($text);
        if ($batchNo) {
            $headerFields['batch_number'] = $batchNo;
        }

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
     * Detects if the text matches the MCI 360 Control System format.
     */
    protected function isMci360Report(string $text): bool
    {
        return stripos($text, 'MCI 360') !== false 
            || stripos($text, 'Autographic Record') !== false 
            || (stripos($text, 'Docket / Batch Report') !== false && stripos($text, 'Mass of Total Set weight') !== false);
    }

    /**
     * Dedicated parser for MCI 360 Control System Ver 1.0 reports.
     */
    protected function parseMci360(string $text): array
    {
        $headerFields = [];
        
        $patterns = [
            'batch_date' => '/(?:Batch Date)\s*[:=]?\s*([0-9]{1,2}-[A-Za-z]{3}-[0-9]{4}|[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4})/i',
            'plant_serial' => '/(?:Plant Serial Number|Plant S\/?N)\s*[:=]?\s*([A-Za-z0-9\-]+)/i',
            'batch_start_time' => '/(?:Batch Start Time|Start Time)\s*[:=]?\s*([0-9]{1,2}:[0-9]{2}(?::[0-9]{2})?)/i',
            'batch_end_time' => '/(?:Batch End Time|End Time)\s*[:=]?\s*([0-9]{1,2}:[0-9]{2}(?::[0-9]{2})?)/i',
            'batch_number' => '/(?:Batch Number|Batch No\.?)\s*[:=]?\s*([A-Za-z0-9\-]+)/i',
            'recipe_code' => '/(?:Recipe Code)\s*[:=]?\s*([^\n\r]+)/i',
            'recipe_name' => '/(?:Recipe Name)\s*[:=]?\s*([^\n\r]+)/i',
            'truck_number' => '/(?:Truck Number|Truck No\.?)\s*[:=]?\s*([A-Za-z0-9\-]+)/i',
            'driver' => '/(?:Truck Driver|Driver)\s*[:=]?\s*([^\n\r]+)/i',
            'batcher_name' => '/(?:Batcher Name|Batcher)\s*[:=]?\s*([^\n\r]+)/i',
            'customer' => '/(?:Customer)\s*[:=]?\s*([^\n\r]+)/i',
            'site' => '/(?:Site)\s*[:=]?\s*([^\n\r]+)/i',
            'order_number' => '/(?:Order Number|Order No\.?)\s*[:=]?\s*([^\n\r]+)/i',
            'mixer_capacity' => '/(?:Mixer Capacity)\s*[:=]?\s*([0-9\.]+)\s*(?:M³|m3|M3)?/i',
            'batch_size' => '/(?:Production Quantity|With This Load|Batch Size)\s*[:=]?\s*([0-9\.]+)\s*(?:M³|m3|M3)?/i',
            'ordered_qty' => '/(?:Ordered Quantity)\s*[:=]?\s*([0-9\.]+)\s*(?:M³|m3|M3)?/i',
            'production_qty' => '/(?:Production Quantity)\s*[:=]?\s*([0-9\.]+)\s*(?:M³|m3|M3)?/i',
            'total_set_weight' => '/(?:Mass of Total Set weight in Kgs\.?|Total Set Weight)\s*[:=]?\s*([0-9\.,]+)/i',
            'total_actual_weight' => '/(?:Mass of Total Actual in Kgs\.?|Total Actual)\s*[:=]?\s*([0-9\.,]+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $headerFields[$key] = trim($match[1]);
            }
        }

        // Extract Material breakdown for MCI 360
        $materialRows = $this->parseMci360Materials($text);

        return [
            'headerFields' => $headerFields,
            'materialRows' => $materialRows,
        ];
    }

    /**
     * Parses the columnar aggregate/cement/water/admixture matrix from MCI 360 reports.
     */
    protected function parseMci360Materials(string $text): array
    {
        $materials = [];

        // MCI 360 standard column names in header
        $colNames = ['MSA1', 'MSA2', '12MM', '6MM', '20MM', 'Agg6', 'CEM 1', 'CEM 2', 'CEM 3', 'CEM 4', 'CEM 5', 'WAT', 'Wtr2', 'Wtr3', 'ADM 1', 'ADM 2', 'Admi 3', 'Admi 4', 'Silica'];

        // Try extracting Total Set Weight row and Total Actual row
        $setWeights = [];
        $actualWeights = [];

        if (preg_match('/Total Set Weight in Kgs\.?\s*\n([0-9\.\s\-]+)/i', $text, $setMatch)) {
            $setWeights = $this->extractNumbers($setMatch[1]);
        }
        
        if (preg_match('/Total Actual in Kgs\.?\s*\n([0-9\.\s\-]+)/i', $text, $actMatch)) {
            $actualWeights = $this->extractNumbers($actMatch[1]);
        }

        // Pair column names with weights
        $maxCount = max(count($setWeights), count($actualWeights));
        for ($i = 0; $i < $maxCount; $i++) {
            $target = $setWeights[$i] ?? 0.0;
            $actual = $actualWeights[$i] ?? 0.0;
            
            // Only include non-zero materials in active batch
            if ($target > 0 || $actual > 0) {
                $name = $colNames[$i] ?? ("Material " . ($i + 1));
                $materials[] = [
                    'material_name' => $name,
                    'target_qty' => $target,
                    'actual_qty' => $actual,
                    'deviation_quantity' => round($actual - $target, 3),
                ];
            }
        }

        // Fallback to standard columnar parsing if empty
        if (empty($materials)) {
            $materials = $this->parseColumnar($text);
        }

        return $materials;
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
