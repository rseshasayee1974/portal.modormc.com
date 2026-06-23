<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BatchSheetUpload;
use Illuminate\Support\Facades\Storage;

$uploads = BatchSheetUpload::all();
echo "Total uploads: " . $uploads->count() . "\n";
foreach ($uploads as $upload) {
    $existsLocal = file_exists(storage_path('app/' . $upload->stored_path));
    $existsPublic = Storage::disk('public')->exists($upload->stored_path);
    echo "ID: {$upload->id}, Status: {$upload->status}, File: {$upload->original_filename}\n";
    echo "  Stored path: {$upload->stored_path}\n";
    echo "  Local file exists: " . ($existsLocal ? 'YES' : 'NO') . "\n";
    echo "  Public disk file exists: " . ($existsPublic ? 'YES' : 'NO') . "\n";
    if ($upload->error_message) {
        echo "  Error: {$upload->error_message}\n";
    }
}
