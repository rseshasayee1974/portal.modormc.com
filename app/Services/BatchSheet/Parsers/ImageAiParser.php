<?php

namespace App\Services\BatchSheet\Parsers;

use App\Services\BatchSheet\Contracts\DocumentParser;
use App\Services\BatchSheet\DTOs\ParsedDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageAiParser implements DocumentParser
{
    public function canHandle(string $mimeType, string $extension): bool
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/tiff', 'image/bmp', 'image/webp'];
        return in_array($mimeType, $imageMimes, true) || $extension === 'pdf'; // PDF can be handled as scanned PDF
    }

    public function getParserName(): string
    {
        return 'AI Vision Parser';
    }

    public function parse(string $filePath, array $options = []): ParsedDocument
    {
        Log::info("ImageAiParser: Processing file {$filePath}");

        $geminiKey = config('ai.providers.gemini.key') ?? config('services.gemini.key') ?? env('GEMINI_API_KEY');
        $openaiKey = config('ai.providers.openai.key') ?? config('services.openai.key') ?? env('OPENAI_API_KEY');

        if (!$geminiKey && !$openaiKey) {
            throw new \RuntimeException(
                'No AI Vision API key configured. Please configure GEMINI_API_KEY or OPENAI_API_KEY in your .env file.'
            );
        }

        $fileContent = file_get_contents($filePath);
        $base64Data = base64_encode($fileContent);
        
        // Determine mime type
        $mimeType = $options['mime_type'] ?? $this->detectMimeType($filePath);

        $prompt = <<<PROMPT
You are an expert document parsing assistant specialized in Concrete Batching Plant reports (such as SCHWING Stetter MCI 70, Command Alkon, Aquarius, Macons, Liebherr).
Your task is to extract the header information and the material weights from the uploaded batch sheet report (PDF or photo).

IMPORTANT EXTRACTION GUIDELINES:
1. HEADER:
   - "batch_number": The Docket Number or Batch Number (e.g. "338").
   - "batch_date": Date of batching (e.g. "2024-04-26" or "26-04-2024").
   - "batch_start_time": Start time (e.g. "09:16").
   - "batch_end_time": End time (e.g. "09:45").
   - "batch_size": Total load / production quantity in m3 / cu.m (e.g. "Production Quantity" or "Order Quantity" or "With This Load", e.g. 7.5).
   - "customer": Customer name (e.g. "PRABU SIVARAJ").
   - "truck_number": Transit mixer or vehicle plate registration (e.g. "TN32BD2738").
   - "driver": Truck driver name (e.g. "EALUMALAI").
   - "recipe_name": Mix design grade / name (e.g. "M 30").
   - "recipe_code": Mix design code (e.g. "M 30").
   - "batcher_name": Operator / batcher name or machine system (e.g. "Stetter").

2. MATERIALS TABLE:
   - Concrete batch sheets often display multiple batching cycles (e.g. 15 batches of 0.5 m3) and conclude with summary rows: "Total Set Weight in Kgs" and "Total Actual Weight in Kgs".
   - Extract the overall TOTAL weights for each material column across the entire docket:
     * "material_name": Clean material column name (e.g. "SAND", "12MM", "20MM", "CEMENT", "WATER", "ADMIX1" / "ADMIXTURE"). Ignore columns with only 0 or pure moisture % (MOI).
     * "target_qty": Total Set Weight in Kgs (e.g. for SAND: 6232.5, 12MM: 2925, 20MM: 5430, CEMENT: 2850, WATER: 952.5, ADMIX1: 11.25).
     * "actual_qty": Total Actual Weight in Kgs (e.g. for SAND: 6234.5, 12MM: 2941, 20MM: 5423, CEMENT: 2850, WATER: 944.5, ADMIX1: 7.25).
     * "deviation_quantity": actual_qty - target_qty.

3. CONFIDENCE & FIELD SCORES:
   - Provide realistic confidence score (0-100) based on visual clarity and OCR accuracy.
   - Provide field_scores (0-100) for each extracted header field.

Return ONLY a valid JSON object. Do NOT wrap in markdown code blocks or backticks. No commentary.

JSON format:
{
  "header": {
    "batch_number": "338",
    "batch_date": "26-04-2024",
    "batch_start_time": "09:16",
    "batch_end_time": "09:45",
    "batch_size": 7.5,
    "customer": "PRABU SIVARAJ",
    "truck_number": "TN32BD2738",
    "driver": "EALUMALAI",
    "recipe_name": "M 30",
    "recipe_code": "M 30",
    "batcher_name": "Stetter"
  },
  "materials": [
    {
      "material_name": "SAND",
      "target_qty": 6232.5,
      "actual_qty": 6234.5,
      "deviation_quantity": 2.0
    },
    {
      "material_name": "12MM",
      "target_qty": 2925.0,
      "actual_qty": 2941.0,
      "deviation_quantity": 16.0
    },
    {
      "material_name": "20MM",
      "target_qty": 5430.0,
      "actual_qty": 5423.0,
      "deviation_quantity": -7.0
    },
    {
      "material_name": "CEMENT",
      "target_qty": 2850.0,
      "actual_qty": 2850.0,
      "deviation_quantity": 0.0
    },
    {
      "material_name": "WATER",
      "target_qty": 952.5,
      "actual_qty": 944.5,
      "deviation_quantity": -8.0
    },
    {
      "material_name": "ADMIX1",
      "target_qty": 11.25,
      "actual_qty": 7.25,
      "deviation_quantity": -4.0
    }
  ],
  "confidence": 98.0,
  "field_scores": {
    "batch_number": 99,
    "batch_date": 98,
    "batch_size": 98,
    "customer": 97,
    "truck_number": 96,
    "driver": 95,
    "recipe_name": 98
  }
}
PROMPT;

        if ($geminiKey) {
            return $this->parseWithGemini($base64Data, $mimeType, $prompt, $geminiKey);
        } else {
            return $this->parseWithOpenAI($base64Data, $mimeType, $prompt, $openaiKey);
        }
    }

    protected function parseWithGemini(string $base64Data, string $mimeType, string $prompt, string $apiKey): ParsedDocument
    {
        Log::info("ImageAiParser: Calling Gemini Vision API");

        $models = [
            env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'gemini-2.5-flash',
            'gemini-2.5-pro',
        ];
        // Remove duplicates while preserving order
        $models = array_unique(array_filter($models));

        $lastError = '';

        foreach ($models as $model) {
            try {
                Log::info("ImageAiParser: Trying Gemini model: {$model}");

                $response = Http::withoutVerifying()->timeout(45)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                    [
                        'contents' => [[
                            'parts' => [
                                ['text' => $prompt],
                                ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Data]],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.0,
                            'maxOutputTokens' => 8192,
                            'responseMimeType' => 'application/json'
                        ],
                    ]
                );

                if ($response->successful()) {
                    $parts = $response->json('candidates.0.content.parts') ?? [];
                    $text = '';
                    foreach ($parts as $part) {
                        if (!empty($part['thought'])) {
                            continue;
                        }
                        if (isset($part['text'])) {
                            $text .= $part['text'];
                        }
                    }
                    if (empty($text) && !empty($parts[0]['text'])) {
                        $text = $parts[0]['text'];
                    }

                    if (!empty($text)) {
                        return $this->parseJsonResponse($text);
                    }
                }

                $lastError = 'Gemini (' . $model . ') error: ' . $response->status() . ' - ' . $response->body();
                Log::warning("ImageAiParser: {$lastError}");
            } catch (\Exception $e) {
                $lastError = 'Gemini (' . $model . ') exception: ' . $e->getMessage();
                Log::warning("ImageAiParser: {$lastError}");
            }
        }

        throw new \RuntimeException("Gemini Vision API failed on all attempted models. Last error: " . $lastError);
    }

    protected function parseWithOpenAI(string $base64Data, string $mimeType, string $prompt, string $apiKey): ParsedDocument
    {
        Log::info("ImageAiParser: Calling OpenAI GPT-4o API");

        if ($mimeType === 'application/pdf') {
            throw new \RuntimeException('OpenAI GPT-4o does not support parsing PDF files directly. Use Gemini or upload image format.');
        }

        $response = Http::withoutVerifying()->withToken($apiKey)->timeout(45)->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => 'gpt-4o',
                'max_tokens' => 4096,
                'temperature' => 0.0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64Data}"]],
                    ],
                ]],
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status() . ' - ' . $response->body());
        }

        $text = $response->json('choices.0.message.content') ?? '';
        return $this->parseJsonResponse($text);
    }

    protected function parseJsonResponse(string $text): ParsedDocument
    {
        Log::debug("ImageAiParser: Raw API response: " . substr($text, 0, 500));

        // Strip markdown code fences if present
        $jsonText = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $text);
        $jsonText = trim($jsonText);

        $decoded = json_decode($jsonText, true);
        if (!is_array($decoded)) {
            // Try extracting the outermost {...}
            $jsonStart = strpos($jsonText, '{');
            $jsonEnd = strrpos($jsonText, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $subJson = substr($jsonText, $jsonStart, ($jsonEnd - $jsonStart + 1));
                $decoded = json_decode($subJson, true);
            }
        }

        if (!is_array($decoded)) {
            Log::error("ImageAiParser: Failed to decode AI JSON. Raw text: " . $text);
            throw new \RuntimeException("Failed to decode AI response as JSON: " . substr($text, 0, 300));
        }

        $header = $decoded['header'] ?? [];
        $materials = $decoded['materials'] ?? [];
        $confidence = (float)($decoded['confidence'] ?? 80.0);
        $fieldScores = $decoded['field_scores'] ?? [];

        // Build list of headerFields key => value for mapping dictionary
        $headerFields = [];
        foreach ($header as $key => $val) {
            if ($val !== null && $val !== '') {
                // Map camel/snake keys back to friendly names or keep them
                $headerFields[$key] = $val;
            }
        }

        $materialRows = [];
        foreach ($materials as $m) {
            $name = $m['material_name'] ?? $m['item'] ?? $m['name'] ?? null;
            if ($name) {
                $materialRows[] = [
                    'material_name' => $name,
                    'target_qty' => (float)($m['target_qty'] ?? $m['target'] ?? 0),
                    'actual_qty' => (float)($m['actual_qty'] ?? $m['actual'] ?? $m['act'] ?? 0),
                    'deviation_quantity' => (float)($m['deviation_quantity'] ?? $m['deviation'] ?? 0),
                ];
            }
        }

        return new ParsedDocument([
            'rawText' => $jsonText,
            'headerFields' => $headerFields,
            'materialRows' => $materialRows,
            'confidence' => $confidence,
            'parserUsed' => $this->getParserName(),
            'fieldScores' => $fieldScores,
        ]);
    }

    protected function detectMimeType(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'pdf': return 'application/pdf';
            case 'png': return 'image/png';
            case 'webp': return 'image/webp';
            case 'tiff':
            case 'tif': return 'image/tiff';
            case 'bmp': return 'image/bmp';
            default: return 'image/jpeg';
        }
    }
}