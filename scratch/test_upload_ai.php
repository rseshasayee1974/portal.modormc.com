<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BatchSheetUpload;
use App\Services\BatchSheet\Parsers\ImageAiParser;
use Illuminate\Support\Facades\Storage;

$upload = BatchSheetUpload::latest()->first();
echo "Testing with upload #{$upload->id}: {$upload->original_filename}\n";

$filePath = Storage::disk(config('batchsheet.storage_disk', 'public'))->path($upload->stored_path);
echo "File path: {$filePath} (exists: " . (file_exists($filePath) ? 'YES' : 'NO') . ")\n";

if (file_exists($filePath)) {
    $parser = new ImageAiParser();
    try {
        $apiKey = env('GEMINI_API_KEY');
        $fileContent = file_get_contents($filePath);
        $base64Data = base64_encode($fileContent);
        $mimeType = $upload->mime_type;

        $response = \Illuminate\Support\Facades\Http::timeout(60)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}",
            [
                'contents' => [[
                    'parts' => [
                        ['text' => 'Extract batch sheet data as JSON: header {batch_number, batch_date, customer, recipe_name, batch_size}, materials [{material_name, target_qty, actual_qty}]'],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Data]],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'responseMimeType' => 'application/json'
                ],
            ]
        );

        echo "API Status: " . $response->status() . "\n";
        echo "Response snippet:\n" . substr($response->body(), 0, 500) . "\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
