<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BatchSheetUpload;

$uploads = BatchSheetUpload::latest()->take(10)->get();
foreach ($uploads as $u) {
    echo "ID: {$u->id} | File: {$u->original_filename} ({$u->mime_type}) | Status: {$u->status} | OCR: " . ($u->ocr_required ? 'YES' : 'NO') . " | Parser: {$u->parser_used} | Error: {$u->error_message}\n";
}
