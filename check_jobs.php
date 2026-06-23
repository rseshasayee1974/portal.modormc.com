<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$jobs = DB::table('mm_jobs')->get();
echo "PENDING JOBS COUNT: " . count($jobs) . "\n";
foreach ($jobs as $job) {
    echo "Job ID: {$job->id}, Queue: {$job->queue}, Attempts: {$job->attempts}\n";
    $payload = json_decode($job->payload, true);
    echo "DisplayName: " . ($payload['displayName'] ?? 'N/A') . "\n";
}

$failed = DB::table('mm_failed_jobs')->get();
echo "FAILED JOBS COUNT: " . count($failed) . "\n";
foreach ($failed as $f) {
    echo "Failed Job ID: {$f->id}, Connection: {$f->connection}, Queue: {$f->queue}\n";
    echo "Exception: " . substr($f->exception, 0, 500) . "\n\n";
}
