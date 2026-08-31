<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$key = env('GEMINI_API_KEY');

$modelsToTest = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-1.5-flash-latest', 'gemini-2.0-flash'];

foreach ($modelsToTest as $m) {
    echo "Testing model {$m}...\n";
    $response = Http::timeout(15)->post(
        "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$key}",
        [
            'contents' => [[
                'parts' => [
                    ['text' => 'Respond with JSON: {"status": "ok"}'],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json'
            ],
        ]
    );

    echo "Status: " . $response->status() . " | Body: " . substr($response->body(), 0, 150) . "\n\n";
}
