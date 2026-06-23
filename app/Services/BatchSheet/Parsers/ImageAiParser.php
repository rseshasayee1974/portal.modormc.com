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

        $geminiKey = env('GEMINI_API_KEY');
        $openaiKey = env('OPENAI_API_KEY');

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
You are a high-accuracy document parsing assistant for concrete batching plants.
Your task is to extract information from the provided batch sheet report (PDF or image).
You must analyze the layout and extract both the header information and the material weights.
Perform a strict cross-verification to match material labels with their target, actual, and deviation weights.

Return ONLY a valid JSON object structure. Do NOT wrap in markdown blocks, do NOT write markdown fences, do NOT include explanations.

Expected JSON output format:
{
  "header": {
    "batch_number": "docket number or batch number value",
    "batch_date": "date value, e.g. YYYY-MM-DD or DD-MM-YYYY",
    "batch_start_time": "start time value, e.g. HH:MM:SS",
    "batch_end_time": "end time value, e.g. HH:MM:SS",
    "batch_size": 1.5,
    "customer": "customer legal name",
    "site": "site location name",
    "truck_number": "transit mixer or truck plate registration",
    "driver": "driver name",
    "recipe_name": "mix design title, e.g. M30",
    "recipe_code": "mix design code, e.g. M30(N)",
    "order_number": "sales order or work order reference number"
  },
  "materials": [
    {
      "material_name": "D SAND",
      "target_qty": 1300.0,
      "actual_qty": 1301.0,
      "deviation_quantity": 1.0
    }
  ],
  "confidence": 92.5,
  "field_scores": {
    "batch_number": 95,
    "batch_date": 90,
    "batch_size": 95,
    "customer": 85,
    "truck_number": 80,
    "driver": 85,
    "recipe_name": 90
  }
}

Ensure all materials (e.g. D Sand, M Sand, Cement, Water, Admixture) are extracted. If target or actual weights are zero or empty, still include them with 0.
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

        // If mime type is not supported by Gemini (e.g. tiff/bmp), we might fallback, but flash supports pdf, png, jpeg, webp
        // Normalize TIFF or BMP to png in general, but let's send it directly first
        $response = Http::timeout(45)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
            [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Data]],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'maxOutputTokens' => 2048,
                    'responseMimeType' => 'application/json'
                ],
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini Vision API error: ' . $response->status() . ' - ' . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';
        return $this->parseJsonResponse($text);
    }

    protected function parseWithOpenAI(string $base64Data, string $mimeType, string $prompt, string $apiKey): ParsedDocument
    {
        Log::info("ImageAiParser: Calling OpenAI GPT-4o API");

        if ($mimeType === 'application/pdf') {
            throw new \RuntimeException('OpenAI GPT-4o does not support parsing PDF files directly. Use Gemini or upload image format.');
        }

        $response = Http::withToken($apiKey)->timeout(45)->post(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => 'gpt-4o',
                'max_tokens' => 2048,
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
            throw new \RuntimeException("Failed to decode AI response as JSON: " . substr($text, 0, 200));
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
