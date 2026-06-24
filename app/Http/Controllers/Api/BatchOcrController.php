<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Smalot\PdfParser\Parser as PdfParser;

class BatchOcrController extends Controller
{
        private array $lastAiErrors = [];   // <— add this

    private const CATEGORY_RULES = [
        'Silica' => '/silica|microsilica|sil|ms/i',
        'Admixture' => '/admix|adm|plastic|superplastic|hyper|retarder|accelerator|chemical|additive/i',
        'Water' => '/water|wtr|ice|wc|moisture/i',
        'Cement' => '/cement|cem|opc|ppc|psc|slag|ggbs|ggbfs|fly|flyash|pfa|ash/i',
        'Aggregate' => '/sand|msand|psand|csand|dsand|rsand|dust|jelly|metal|chips|agg|aggregate|6mm|10mm|12mm|20mm|40mm|grit|moi|stone/i',
    ];

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:jpg,jpeg,png,webp,pdf,xls,xlsx,csv,doc,docx,txt',
            'batch_id' => 'nullable|integer',
            'batch_no' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $sourceType = $this->detectSource($ext, $mime);

       try {
    $parsed = match($sourceType) {
        'excel' => $this->parseSpreadsheet($file),
        'pdf' => $this->parsePdf($file),
        'text' => $this->parseDocument($file),
        default => $this->parseImage($file),
    };

    $materials = $parsed['materials']?? [];
    $batchNo = $parsed['batch_no']?: $request->input('batch_no', 'Unknown');

    $originalPath = $file->store('batch-sheets/originals', 'public');
    $this->updateBatchPaths($request->input('batch_id'), $originalPath);

    // SUCCESS
    if (!empty($materials)) {
        return response()->json([
            'status' => true,
            'message' => count($materials).' material(s) parsed.',
            'data' => $this->responseData($sourceType, $batchNo, $originalPath, $originalPath, $materials),
        ]);
    }

    // EMPTY — AI down or unreadable
    return response()->json([
        'status' => false,
        'manual_entry_required' => true,
        'message' => 'Automatic reading failed (AI services unavailable, or image unclear). Please enter the weights manually.',
        'errors' => $this->lastAiErrors,
        'data' => $this->responseData($sourceType, $batchNo, $originalPath, $originalPath, []),
    ], 200); // 200 so frontend can handle gracefully

} catch (\Exception $e) {
    Log::error('BatchOCR fatal', ['error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);

    // store original anyway
    $originalPath = isset($file)? $file->store('batch-sheets/originals', 'public') : null;

    return response()->json([
        'status' => false,
        'manual_entry_required' => true,
        'message' => 'System error while reading file. Please enter data manually.',
        'errors' => array_merge($this->lastAiErrors, [$e->getMessage()]),
        'data' => [
            'source' => $sourceType?? 'unknown',
            'batch_no' => $request->input('batch_no', ''),
            'original_url' => $originalPath? Storage::url($originalPath) : null,
            'materials' => [],
        ]
    ], 200);
}
    }

    // ── File type routing ─────────────────────────────────────────────
    private function detectSource(string $ext, string $mime): string
    {
        return match(true) {
            in_array($ext, ['xls','xlsx','csv']) => 'excel',
            $ext === 'pdf' || $mime === 'application/pdf' => 'pdf',
            in_array($ext, ['doc','docx','txt']) || str_contains($mime, 'text/') => 'text',
            default => 'image',
        };
    }

    // ── Parsers ───────────────────────────────────────────────────────
    private function parsePdf($file): array
    {
        if (!class_exists(PdfParser::class)) {
            throw new \RuntimeException('Run: composer require smalot/pdfparser');
        }
        $text = (new PdfParser())->parseFile($file->getRealPath())->getText();
        return $this->parseFromText($text);
    }

    private function parseSpreadsheet($file): array
    {
        $sheet = IOFactory::load($file->getRealPath());
        $text = '';
        foreach ($sheet->getWorksheetIterator() as $ws) {
            foreach ($ws->toArray() as $row) {
                $text.= implode("\t", array_filter($row, fn($v) => $v!== null)). "\n";
            }
        }
        return $this->parseFromText($text);
    }

    private function parseDocument($file): array
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', file_get_contents($file->getRealPath()));
        return $this->parseFromText($text);
    }

    private function parseFromText(string $text): array
    {
        $batchNo = $this->extractBatchNo($text);
        $materials = $this->extractMaterials($text);

        if (empty($materials)) {
            $materials = $this->tryAiProviders(null, $text)['materials']?? [];
        }

        foreach ($materials as &$m) {
            $m['category'] = $m['category']?? $this->categorize($m['item']);
        }

        return ['batch_no' => $batchNo, 'materials' => $materials];
    }

    private function parseImage($file): array
    {
        return $this->tryAiProviders($file, null);
    }

    // ── Material extraction strategies ────────────────────────────────
    private function extractMaterials(string $text): array
    {
        return $this->parseColumnar($text)
           ?: $this->parseRowPerMaterial($text)
           ?: $this->parseKeyValue($text)
           ?: [];
    }

    private function parseColumnar(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $headers = [];
        foreach ($lines as $i => $line) {
            if (!$headers &&!$this->isNumericLine($line)) {
                $tokens = array_values(array_filter(preg_split('/\s{2,}|\t+/', trim($line))));
                if (count(array_filter($tokens, fn($t) => preg_match('/^[A-Z0-9][A-Z0-9\s\/\-]{0,15}$/', $t))) >= 3) {
                    $headers = $tokens;
                }
            }
            if ($headers && preg_match('/total\s+actual/i', $line)) {
                $nums = $this->extractNumbers($line. ' '. ($lines[$i+1]?? ''));
                $out = [];
                foreach ($headers as $idx => $name) {
                    if (isset($nums[$idx])) $out[] = ['item' => $name, 'actual' => $nums[$idx]];
                }
                return $out;
            }
        }
        return [];
    }

    private function parseRowPerMaterial(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9\s\/\-]{1,18})\s{2,}([\d\s.,]+)$/', trim($line), $m)) {
                $nums = $this->extractNumbers($m[2]);
                $actual = $nums[1]?? $nums[0]?? 0;
                if ($actual > 0) $out[] = ['item' => trim($m[1]), 'actual' => $actual];
            }
        }
        return $out;
    }

    private function parseKeyValue(string $text): array
    {
        preg_match_all('/([A-Z][A-Z0-9\s\/\-]{1,18})\s*[:=]\s*([\d,]+\.?\d*)/m', $text, $m, PREG_SET_ORDER);
        return array_map(fn($x) => ['item' => trim($x[1]), 'actual' => (float)str_replace(',', '', $x[2])], $m);
    }

    // ── AI ────────────────────────────────────────────────────────────
    private function tryAiProviders($file,?string $text): array
    {
            $this->lastAiErrors = [];

        $providers = [
            'gemini' => ['key' => env('GEMINI_API_KEY'), 'fn' => fn($k) => $this->callGemini($file, $text, $k)],
            'openai' => ['key' => env('OPENAI_API_KEY'), 'fn' => fn($k) => $this->callOpenAI($file, $text, $k, 'https://api.openai.com/v1/chat/completions', ['gpt-4o-mini'])],
            'groq' => ['key' => env('GROQ_API_KEY'), 'fn' => fn($k) => $this->callOpenAI($file, $text, $k, 'https://api.groq.com/openai/v1/chat/completions', ['llama-3.2-90b-vision-preview'])],
            'kimi' => ['key' => env('KIMI_API_KEY'), 'fn' => fn($k) => $this->callOpenAI($file, $text, $k, 'https://api.moonshot.cn/v1/chat/completions', ['moonshot-v1-8k-vision-preview'])],
            'sarvam' => ['key' => env('SARVAM_API_KEY'), 'fn' => fn($k) => $this->callOpenAI($file, $text, $k, 'https://api.sarvam.ai/v1/chat/completions', ['sarvam-vision-preview'])],
        ];

           foreach ($providers as $name => $p) {
        if (!$p['key']) {
            $this->lastAiErrors[] = "$name: missing API key";
            continue;
        }
        try {
            $result = $p['fn']($p['key']);
            if (!empty($result['materials'])) return $result;
        } catch (\Exception $e) {
            $msg = "$name: ".$e->getMessage();
            $this->lastAiErrors[] = $msg;
            Log::warning('BatchOCR provider failed', ['provider'=>$name, 'error'=>$e->getMessage()]);
        }
    }
    // all failed — return empty, don't throw
    return ['batch_no' => '', 'materials' => []];
    }

    private function callGemini($file,?string $text, string $key): array
    {
        $parts = [['text' => $this->aiPrompt($text)]];
        if ($file) $parts[] = ['inline_data' => ['mime_type' => $file->getMimeType(), 'data' => base64_encode(file_get_contents($file->getRealPath()))]];

        $resp = Http::withoutVerifying()->timeout(90)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=$key", [
            'contents' => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json'],
        ]);
        if (!$resp->successful()) throw new \RuntimeException($resp->body());
        return $this->normalizeAiResponse($resp->json('candidates.0.content.parts.0.text'));
    }

    private function callOpenAI($file,?string $text, string $key, string $url, array $models): array
    {
        $content = [['type' => 'text', 'text' => $this->aiPrompt($text)]];
        if ($file &&!$text) $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath())), 'detail' => 'high']];

        $resp = Http::withoutVerifying()->withToken($key)->timeout(90)->post($url, [
            'model' => $models[0], 'temperature' => 0, 'messages' => [['role' => 'user', 'content' => $content]], 'response_format' => ['type' => 'json_object']
        ]);
        if (!$resp->successful()) throw new \RuntimeException($resp->body());
        Log::info('BatchOCR provider response', ['model'=>$models, 'response'=>$resp->json()]);
        return $this->normalizeAiResponse($resp->json('choices.0.message.content'));
    }

    private function aiPrompt(?string $text): string
    {
        $promptFile = __DIR__.'/prompts/batch_ocr.txt';
        $base = file_exists($promptFile) ? file_get_contents($promptFile) : $this->getAiPrompt();
        return $text ? $base . "\n\n--- TEXT ---\n" . $text : $base;
    }

    // ── Helpers ───────────────────────────────────────────────────────
    private function updateBatchPaths(?int $id, string $path): void
    {
        if (!$id ||!($b = Batch::find($id))) return;
        collect([$b->batch_sheet_path, $b->batch_original_sheet_path])->filter()->each(fn($p) => Storage::disk('public')->delete($p));
        $b->update(['batch_sheet_path' => $path, 'batch_original_sheet_path' => $path]);
    }

    private function responseData(string $src, string $batchNo, string $excel, string $orig, array $mats): array
    {
        return ['source' => $src, 'batch_no' => $batchNo, 'path' => $excel, 'url' => Storage::url($excel), 'original_url' => Storage::url($orig), 'materials' => $mats];
    }

    private function normalizeAiResponse(string $text): array
    {
        $json = json_decode(preg_replace('/```json|```/', '', $text), true);
        $mats = $json['materials']?? $json?? [];
        $mats = array_values(array_filter(array_map(fn($r) => isset($r['item'])? [
            'item' => trim($r['item']),
            'actual' => (float)($r['actual']?? 0),
            'category' => $r['category']?? $this->categorize($r['item'])
        ] : null, $mats)));
        return ['batch_no' => $json['batch_no']?? '', 'materials' => $mats];
    }

    private function categorize(string $name): string
    {
        foreach (self::CATEGORY_RULES as $cat => $rx) {
            if (preg_match($rx, $name)) return $cat;
        }
        return 'Aggregate';
    }

    private function extractBatchNo(string $text): string
    {
        return preg_match('/(?:Batch|Docket|Delivery|Order)\s*(?:Number|No\.?)?\s*[:=]?\s*([A-Z0-9\-\/]+)/i', $text, $m)? $m[1] : '';
    }

    private function extractNumbers(string $s): array { preg_match_all('/\d+(?:[.,]\d+)?/', $s, $m); return array_map(fn($n)=>(float)str_replace(',','',$n), $m[0]); }
    private function isNumericLine(string $l): bool { return strlen(preg_replace('/[\d\s.,\-+%\/()]/', '', $l)) <= 3; }

    // keep your original long prompt here
    private function getAiPrompt(): string { /*... your existing prompt... */ return <<<PROMPT
You are a forensic OCR extractor for concrete batch reports. The image or text may be:
- printed Schwing Stetter / Liebherr / Ajax
- a low-res phone photo, skewed, with shadows
- a small-plant handwritten docket
- extracted tabular text from PDF/Excel
- in English or mixed English/Tamil/Hindi numerals

GOAL: Return ONLY batch number and the FINAL ACTUAL weight for every material. No guessing.

--- UNIVERSAL RULES ---

1. BATCH ID (search everywhere, top 40% of page first)
   Extract the FIRST non-empty value after any of these labels (case-insensitive, with or without colon, handwritten allowed):
   "Batch Number", "Batch No", "Batch No.", "Docket Number", "Docket No", "Delivery No", "Ticket No", "Load No", "Order No"
   Keep exactly as printed: "550", "21128.00", "338", "TN22DC5577/2". Return as string.

2. DETECT LAYOUT (do not assume columns)
   A) COLUMN-WISE (most common): material names are across the top, numbers go down
   B) ROW-WISE (handwritten/small plants): first column = material name, next columns = Target / Actual
   Decide by looking: if you see >5 material names in one horizontal line → COLUMN-WISE. Otherwise → ROW-WISE.

3. FIND THE "ACTUAL" VALUES
   COLUMN-WISE:
      - Find the row labeled (exact or fuzzy): "Total Actual Weight", "Total Actual", "Actual in Kgs", "Actual Qty", "Actual"
      - If that row is missing, SUM all rows labeled "Actual Values in kg" for each column.
      - Read numbers LEFT-TO-RIGHT under each material header. Keep negatives (-10) and decimals.

   ROW-WISE:
      - Find the column headed "Actual", "Act.", "Actual Wt", "Achieved"
      - Read each material row left-to-right.

4. BUILD MATERIAL LIST (never skip zeros)
      - For COLUMN-WISE: join multi-line headers ("D" + "SAND" = "D SAND", "20M"+"M"="20MM"). Trim spaces for output but keep original form in "item".
      - DROP columns where header is exactly: "0", "NA", "N/A", "-", blank, "Total", "Difference", "%"
      - KEEP columns even if value = 0, 0.00, or unreadable (use 0 if unreadable, do NOT drop)
      - If same name repeats, make unique: "CEMENT", "CEMENT_2", "CEMENT_3"

5. CATEGORIZE INTO 5 BUCKETS (use name, not position)
   Normalize name: lowercase, remove all spaces/punctuation for matching only.
      - Aggregate: sand, msand, psand, csand, dsand, rsand, dust, jelly, metal, chips, agg, aggregate, 6mm, 10mm, 12mm, 20mm, 40mm, 12m, 20m, grit, moi, stone
      - Cement: cem, cement, opc, ppc, psc, slag, ggbs, ggbfs, fly, flyash, pfa, ash
      - Water: water, wtr, wtr1, wtr2, ice, wc, moisture
      - Admixture: admix, adm, admixture, plasticizer, superplasticizer, hyper, retarder, accelerator, chemical, additive
      - Silica: silica, microsilica, sil, ms
   If unsure, choose the closest bucket. Never create a 6th category.

6. HANDWRITTEN / POOR QUALITY RULES
      - Read digit-by-digit: 0 vs 8, 1 vs 7, 5 vs 6. Use surrounding numbers as context.
      - If a cell is smudged, output 0 but KEEP the material.
      - Do not invent materials that are not visible.

7. VALIDATION (must pass before output)
      - Count materials: must be >=3
      - Sum of all "actual" > 0
      - Cross-check: for COLUMN-WISE, sum of individual "Actual Values" rows should ≈ Total Actual (±3%). If mismatch, trust Total Actual row.

8. OUTPUT
   Return ONLY this JSON, no markdown, no explanation:
   {
     "batch_no": "string",
     "materials": [
       {"category": "Aggregate", "item": "D SAND", "actual": 1301},
       {"category": "Aggregate", "item": "M SAND", "actual": 2011}
     ]
   }
      - "actual" is a number (float), not string
      - Keep original header spelling in "item"
      - If nothing found: {"batch_no":"","materials":[]}
PROMPT; }
}