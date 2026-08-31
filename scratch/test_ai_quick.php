<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$key = env('GEMINI_API_KEY');
$openaiKey = env('OPENAI_API_KEY');

// Test OpenAI
if ($openaiKey) {
    echo "Testing OpenAI gpt-4o-mini...\n";
    try {
        $res = Http::withToken($openaiKey)->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => 'Say {"status": "ok"} in json']],
            'response_format' => ['type' => 'json_object'],
        ]);
        echo "OpenAI Status: " . $res->status() . " | " . $res->body() . "\n";
    } catch (\Exception $e) {
        echo "OpenAI Error: " . $e->getMessage() . "\n";
    }
}

// Test Gemini flash-latest with 30s timeout
if ($key) {
    echo "Testing Gemini gemini-flash-latest...\n";
    try {
        $res = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$key}",
            [
                'contents' => [[
                    'parts' => [
                        ['text' => 'Say {"status": "ok"} in json'],
                    ],
                ]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ],
            ]
        );
        echo "Gemini Status: " . $res->status() . " | " . $res->body() . "\n";
    } catch (\Exception $e) {
        echo "Gemini Error: " . $e->getMessage() . "\n";
    }
}
