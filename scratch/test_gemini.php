<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$key = env('GEMINI_API_KEY');
echo "Gemini Key exists: " . ($key ? 'YES (length ' . strlen($key) . ')' : 'NO') . "\n";

$openaiKey = env('OPENAI_API_KEY');
echo "OpenAI Key exists: " . ($openaiKey ? 'YES (length ' . strlen($openaiKey) . ')' : 'NO') . "\n";

if ($key) {
    echo "--- Listing Gemini Models ---\n";
    $res = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$key}");
    echo "Status: " . $res->status() . "\n";
    if ($res->successful()) {
        $models = $res->json('models') ?? [];
        foreach ($models as $m) {
            if (str_contains($m['name'], 'flash') || str_contains($m['name'], 'gemini-1.5') || str_contains($m['name'], 'gemini-2') || str_contains($m['name'], 'gemini')) {
                echo "Model: " . $m['name'] . " - Methods: " . implode(', ', $m['supportedGenerationMethods'] ?? []) . "\n";
            }
        }
    } else {
        echo "Error response: " . $res->body() . "\n";
    }
}
