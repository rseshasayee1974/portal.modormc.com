<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BatchOcrController
 * 
 * Dynamically parses uploaded batch sheet files (PDF or image) to extract
 * "Total Actual Weight" values per material, then returns them as a normalized
 * JSON list that the frontend can directly apply to the Input Reconciliation table.
 * 
 * Parse strategy (in priority order):
 *  1. PDF  → smalot/pdfparser (text extraction) → regex strategies
 *  2. Image → Gemini Vision API  (if GEMINI_API_KEY is set in .env)
 *  3. Image → OpenAI GPT-4o API (if OPENAI_API_KEY is set in .env)
 * 
 * Generates an Excel file with categorized materials and links it to the batch.
 */
class BatchOcrController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:jpg,jpeg,png,webp,pdf',
        ]);

        $file     = $request->file('file');
        $mimeType = $file->getMimeType();
        $isPdf    = $mimeType === 'application/pdf';

        try {
            $parsed = $isPdf
                ? $this->parsePdf($file)
                : $this->parseImage($file);

            $materials = $parsed['materials'] ?? [];
            $batchNo   = $parsed['batch_no'] ?? $request->input('batch_no', 'Unknown');

            // ── 1. Store the original uploaded image/PDF ──────────────────
            $originalPath = $file->store('batch-sheets/originals', 'public');

            // ── 2. Generate and Store Excel File ─────────────────────────
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Safe sheet name (max 31 chars, no invalid chars)
            $safeSheetName = preg_replace('/[\[\]\*\/\:\\\?]/', '', $batchNo);
            $safeSheetName = substr($safeSheetName ?: 'Batch', 0, 31);
            $sheet->setTitle($safeSheetName);

            // Headers
            $sheet->setCellValue('A1', 'Category');
            $sheet->setCellValue('B1', 'Material Name');
            $sheet->setCellValue('C1', 'Actual Weight');

            // Data rows
            $row = 2;
            foreach ($materials as $m) {
                $sheet->setCellValue('A' . $row, $m['category'] ?? '');
                $sheet->setCellValue('B' . $row, $m['item'] ?? '');
                $sheet->setCellValue('C' . $row, $m['actual'] ?? 0);
                $row++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $excelFileName = 'batch-sheets/excel/batch_' . time() . '_' . \Illuminate\Support\Str::slug($batchNo) . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), 'excel');
            $writer->save($tempFile);
            \Illuminate\Support\Facades\Storage::disk('public')->put($excelFileName, file_get_contents($tempFile));
            @unlink($tempFile);

            // ── 3. Update the Batch record ────────────────────────────────
            $batchId = $request->input('batch_id');
            if ($batchId) {
                $batch = \App\Models\Batch::find($batchId);
                if ($batch) {
                    // Delete old files if they exist
                    if ($batch->batch_sheet_path) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($batch->batch_sheet_path);
                    }
                    if ($batch->batch_original_sheet_path) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($batch->batch_original_sheet_path);
                    }
                    $batch->update([
                        'batch_sheet_path'          => $excelFileName,   // Excel file
                        'batch_original_sheet_path' => $originalPath,    // Original image/PDF
                    ]);
                }
            }

            if (empty($materials)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Could not find any material weights in the uploaded file. Files stored successfully.',
                    'data'    => [
                        'source'       => $isPdf ? 'pdf' : 'image',
                        'batch_no'     => $batchNo,
                        'path'         => $excelFileName,
                        'url'          => \Illuminate\Support\Facades\Storage::url($excelFileName),
                        'original_url' => \Illuminate\Support\Facades\Storage::url($originalPath),
                        'materials'    => [],
                    ],
                ], 422);
            }

            return response()->json([
                'status'  => true,
                'message' => count($materials) . ' material(s) parsed. Image & Excel stored successfully.',
                'data'    => [
                    'source'       => $isPdf ? 'pdf' : 'image',
                    'batch_no'     => $batchNo,
                    'path'         => $excelFileName,
                    'url'          => \Illuminate\Support\Facades\Storage::url($excelFileName),
                    'original_url' => \Illuminate\Support\Facades\Storage::url($originalPath),
                    'materials'    => $materials,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('BatchOCR parsing failed: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PDF Parsing (no external API needed)
    // ──────────────────────────────────────────────────────────────────────────

    private function parsePdf($file): array
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new \RuntimeException('PDF parser not installed. Run: composer require smalot/pdfparser');
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($file->getRealPath());
        $text   = $pdf->getText();

        Log::debug('BatchOCR PDF text extracted', ['length' => strlen($text), 'preview' => substr($text, 0, 500)]);

        $batchNo = $this->extractBatchNoFromText($text);
        $materials = $this->extractMaterialsFromText($text);

        foreach ($materials as &$m) {
            $m['category'] = $this->categorizeMaterial($m['item']);
        }

        return [
            'batch_no' => $batchNo,
            'materials' => $materials,
        ];
    }

    /**
     * Multi-strategy text parser for batch sheet layouts.
     * Tries strategies in order; returns the first non-empty result.
     */
    private function extractMaterialsFromText(string $text): array
    {
        // Strategy 1: Schwing Stetter columnar format
        //   Header row: D SAND   M SAND   20MM   12MM ...
        //   "Total Actual Weight in kg" row: 1301   2011   2482 ...
        $result = $this->parseColumnarBatchSheet($text);
        if (!empty($result)) return $result;

        // Strategy 2: Row-per-material format
        //   MATERIAL_NAME   target   ACTUAL
        $result = $this->parseRowPerMaterial($text);
        if (!empty($result)) return $result;

        // Strategy 3: Key–value pairs  (MATERIAL_NAME : ACTUAL  or  = ACTUAL)
        $result = $this->parseKeyValueFormat($text);
        if (!empty($result)) return $result;

        return [];
    }

    /**
     * Strategy 1 – Schwing Stetter columnar layout
     * 
     * The PDF contains a table where:
     *  - Row N:   material names in columns  (D SAND, M SAND, 20MM, ...)
     *  - Row M:   "Total Actual Weight in kg" followed by the actual values
     */
    private function parseColumnarBatchSheet(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $materials   = [];
        $headerNames = [];

        foreach ($lines as $i => $rawLine) {
            $line = trim($rawLine);
            if ($line === '') continue;

            // ── Detect material name header row ─────────────────────────
            // e.g. "D SAND  M SAND  20MM  12MM  CEM2  FLY  WTR  ADM 2"
            // Heuristic: ≥3 tokens that look like material codes (all-caps, short)
            if (empty($headerNames) && !$this->isNumericLine($line)) {
                $tokens = preg_split('/\s{2,}/', $line);
                $tokens = array_values(array_filter(array_map('trim', $tokens)));
                $materialLike = array_filter($tokens, fn($t) => preg_match('/^[A-Z0-9][A-Z0-9\s\/\-]{0,15}$/', $t));

                if (count($materialLike) >= 3) {
                    $headerNames = array_values($materialLike);
                }
            }

            // ── Detect "Total Actual Weight" data row ───────────────────
            if (!empty($headerNames) && preg_match('/total\s+actual\s+weight/i', $line)) {
                // Extract all numbers from this line (or look ahead 1 line)
                $numbers = $this->extractNumbers($line);

                if (count($numbers) < count($headerNames)) {
                    // Numbers might be on the next line
                    $nextLine = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
                    $numbers  = array_merge($numbers, $this->extractNumbers($nextLine));
                }

                foreach ($headerNames as $idx => $name) {
                    if (isset($numbers[$idx]) && $numbers[$idx] > 0) {
                        $materials[] = ['item' => $name, 'actual' => $numbers[$idx]];
                    }
                }

                if (!empty($materials)) return $materials;
            }
        }

        return $materials;
    }

    /**
     * Strategy 2 – Row-per-material tabular format
     * 
     * Each line: MATERIAL_NAME   <target>   <actual>   <deviation>
     * We treat the second-to-last or last number as the actual weight.
     */
    private function parseRowPerMaterial(string $text): array
    {
        $materials = [];
        $lines = preg_split('/\r?\n/', $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $this->isNumericLine($line)) continue;

            // Pattern: starts with a material name, then 2+ numbers
            if (preg_match('/^([A-Z][A-Z0-9\s\/\-]{1,18}?)\s{2,}([\d\s.,]+)$/', $line, $match)) {
                $name    = trim($match[1]);
                $numbers = $this->extractNumbers($match[2]);

                // Actual is usually the 2nd number (target=1st, actual=2nd)
                $actual = count($numbers) >= 2 ? $numbers[1] : ($numbers[0] ?? 0);

                if ($actual > 0 && strlen($name) >= 2) {
                    $materials[] = ['item' => $name, 'actual' => $actual];
                }
            }
        }

        return $materials;
    }

    /**
     * Strategy 3 – Key–value pair format
     * e.g.  "CEMENT : 425.5 kg"  or  "WATER = 182"
     */
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
            $name   = trim($m[1]);
            $actual = (float) str_replace(',', '', $m[2]);
            if ($actual > 0 && strlen($name) >= 2 && strlen($name) <= 22) {
                $materials[] = ['item' => $name, 'actual' => $actual];
            }
        }

        return $materials;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Image Parsing  (AI Vision API)
    // ──────────────────────────────────────────────────────────────────────────

    private function parseImage($file): array
    {
        $geminiKey = env('GEMINI_API_KEY');
        $openaiKey = env('OPENAI_API_KEY');

        if ($geminiKey) {
            return $this->parseWithGemini($file, $geminiKey);
        }

        if ($openaiKey) {
            return $this->parseWithOpenAI($file, $openaiKey);
        }

        throw new \RuntimeException(
            'No AI Vision API key configured for image parsing. ' .
            'Set GEMINI_API_KEY or OPENAI_API_KEY in your .env file, ' .
            'or upload the batch sheet as a PDF instead.'
        );
    }

    private function parseWithGemini($file, string $apiKey): array
    {
        $imageData = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType  = $file->getMimeType();

        $prompt = <<<PROMPT
This is a concrete batch plant production report (e.g. Schwing Stetter, Liebherr, or similar).
Your task is to extract data into a specific JSON structure.
1. Find the "Batch Number" (or Docket Number / Delivery No / Order No if Batch Number is missing).
2. The report might be formatted column-wise (material headers at the top) or row-wise (materials on the left). Carefully analyze the layout and perform a cross-verification (checking both column-wise alignment and row-wise labels, and verifying totals against individual batch rows if present) to strictly ensure each material is accurately matched to its correct "Total Actual Weight" or "Actual Weight" value.
   IMPORTANT: Do NOT skip any material column even if its value is 0 or appears empty. All columns including those with numeric names like "12mm", "20mm", "12M", "20M" must be extracted. A "0" column header between material headers is NOT a material and should be ignored.
   If a total weight value is 0, still include that material with "actual": 0.
3. Categorize each material into exactly one of these 5 categories based on its name (treat names case-insensitively and ignore any spaces, tabs, or erratic spacing; e.g. 'cem 1', 'c e m 1', 'cem1' are all 'Cement'):
   - "Aggregate" (e.g. C Sand, M Sand, P Sand, Sand, 12mm, 20mm, D Sand)
   - "Cement" (e.g. cem1, cem2, cem3, cem4, cem, GGBS, Cement 1, cement 2, cement 3, FLy)
   - "Water" (e.g. wtr1, WTR, WC, wtr 2, ICE, Water)
   - "Admixture" (e.g. admix1, admix 2, adm 1, adm 2, admix-1, Admixture, aditive)
   - "Silica" (e.g. silica, sil)

Return ONLY a valid JSON object — no explanation, no markdown:
{
  "batch_no": "12345",
  "materials": [
    {"category": "Aggregate", "item": "MATERIAL NAME", "actual": 1234.5}
  ]
}
If materials cannot be found, return {"batch_no": "", "materials": []}
PROMPT;

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
            [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageData]],
                    ],
                ]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 1024],
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API error: ' . $response->status() . ' – ' . substr($response->body(), 0, 200));
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';
        Log::debug('BatchOCR Gemini response', ['text' => $text]);

        return $this->parseJsonResponse($text);
    }

    private function parseWithOpenAI($file, string $apiKey): array
    {
        $imageData = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType  = $file->getMimeType();

        $prompt = <<<PROMPT
This is a concrete batch plant production report (e.g. Schwing Stetter, Liebherr, or similar).
Your task is to extract data into a specific JSON structure.
1. Find the "Batch Number" (or Docket Number / Delivery No / Order No if Batch Number is missing).
2. The report might be formatted column-wise (material headers at the top) or row-wise (materials on the left). Carefully analyze the layout and perform a cross-verification (checking both column-wise alignment and row-wise labels, and verifying totals against individual batch rows if present) to strictly ensure each material is accurately matched to its correct "Total Actual Weight" or "Actual Weight" value.
   IMPORTANT: Do NOT skip any material column even if its value is 0 or appears empty. All columns including those with numeric names like "12mm", "20mm", "12M", "20M" must be extracted. A "0" column header between material headers is NOT a material and should be ignored.
   If a total weight value is 0, still include that material with "actual": 0.
3. Categorize each material into exactly one of these 5 categories based on its name (treat names case-insensitively and ignore any spaces, tabs, or erratic spacing; e.g. 'cem 1', 'c e m 1', 'cem1' are all 'Cement'):
   - "Aggregate" (e.g. C Sand, M Sand, P Sand, Sand, 12mm, 20mm, D Sand)
   - "Cement" (e.g. cem1, cem2, cem3, cem4, cem, GGBS, Cement 1, cement 2, cement 3, FLy)
   - "Water" (e.g. wtr1, WTR, WC, wtr 2, ICE, Water)
   - "Admixture" (e.g. admix1, admix 2, adm 1, adm 2, admix-1, Admixture, aditive)
   - "Silica" (e.g. silica, sil)

Return ONLY a valid JSON object — no explanation, no markdown:
{
  "batch_no": "12345",
  "materials": [
    {"category": "Aggregate", "item": "MATERIAL NAME", "actual": 1234.5}
  ]
}
If materials cannot be found, return {"batch_no": "", "materials": []}
PROMPT;

        $response = Http::withToken($apiKey)->timeout(30)->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model'      => 'gpt-4o',
                'max_tokens' => 512,
                'temperature'=> 0,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text',      'text'      => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}"]],
                    ],
                ]],
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status() . ' – ' . substr($response->body(), 0, 200));
        }

        $text = $response->json('choices.0.message.content') ?? '';
        Log::debug('BatchOCR OpenAI response', ['text' => $text]);

        return $this->parseJsonResponse($text);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Extract a JSON array from an AI text response (handles markdown code fences).
     */
    private function parseJsonResponse(string $text): array
    {
        // Strip markdown code fences if present
        $text = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $text);

        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded) && isset($decoded['materials'])) {
                $materials = array_values(array_filter(
                    array_map(function ($row) {
                        if (!is_array($row)) return null;
                        $item   = trim($row['item']   ?? $row['name']     ?? $row['material'] ?? '');
                        $actual = (float)($row['actual'] ?? $row['act'] ?? $row['weight'] ?? 0);
                        $category = trim($row['category'] ?? $this->categorizeMaterial($item));
                        // Keep material even if actual=0 (e.g. 20mm column that has 0 this batch)
                        return $item ? compact('category', 'item', 'actual') : null;
                    }, $decoded['materials'])
                ));
                $batchNo = $decoded['batch_no'] ?? '';
                return ['batch_no' => $batchNo, 'materials' => $materials];
            }
        }

        // Fallback for old array format
        if (preg_match('/\[.*\]/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                $materials = array_values(array_filter(
                    array_map(function ($row) {
                        if (!is_array($row)) return null;
                        $item   = trim($row['item']   ?? $row['name']     ?? $row['material'] ?? '');
                        $actual = (float)($row['actual'] ?? $row['act'] ?? $row['weight'] ?? 0);
                        $category = trim($row['category'] ?? $this->categorizeMaterial($item));
                        // Keep material even if actual=0
                        return $item ? compact('category', 'item', 'actual') : null;
                    }, $decoded)
                ));
                return ['batch_no' => '', 'materials' => $materials];
            }
        }

        throw new \RuntimeException('AI did not return parseable JSON. Response: ' . substr($text, 0, 300));
    }

    private function extractBatchNoFromText(string $text): string
    {
        if (preg_match('/(?:Batch|Docket|Delivery|Order)\s*(?:Number|No\.?|Num)?\s*[:=]?\s*([a-zA-Z0-9\-\_]+)/i', $text, $match)) {
            return $match[1];
        }
        return '';
    }

    private function categorizeMaterial(string $name): string
    {
        $n = strtolower(trim($name));
        if (preg_match('/silica|sil/i', $n)) return 'Silica';
        if (preg_match('/admix|adm|aditive/i', $n)) return 'Admixture';
        if (preg_match('/water|wtr|wc|ice/i', $n)) return 'Water';
        if (preg_match('/cement|cem|ggbs|fly/i', $n)) return 'Cement';
        return 'Aggregate'; 
    }

    /** Extract all decimal numbers from a string. */
    private function extractNumbers(string $line): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/', $line, $matches);
        return array_map(fn($n) => (float) str_replace(',', '', $n), $matches[0]);
    }

    /** Returns true if the line is mostly numbers (data row, not a header). */
    private function isNumericLine(string $line): bool
    {
        $stripped = preg_replace('/[\d\s.,\-+%\/()]/', '', $line);
        return strlen($stripped) <= 3;
    }
}
