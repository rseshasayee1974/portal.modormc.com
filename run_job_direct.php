<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\ProcessBatchSheetJob;

$uploadId = 5;
echo "Processing upload ID: {$uploadId}\n";

try {
    $job = new ProcessBatchSheetJob($uploadId);
    app()->call([$job, 'handle']);
    echo "Job completed successfully!\n";
} catch (\Throwable $e) {
    echo "Exception caught:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
