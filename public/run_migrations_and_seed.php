<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$app->boot();

$logMsg = "\n=== START RUNNING MIGRATIONS AND SEEDERS ===\n";

try {
    $logMsg .= "Running migrations...\n";
    $status1 = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $logMsg .= "Migration status: $status1\n";
    $logMsg .= "Migration output:\n" . \Illuminate\Support\Facades\Artisan::output() . "\n";
    
    $logMsg .= "Running MenuSeeder...\n";
    $status2 = \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'MenuSeeder', '--force' => true]);
    $logMsg .= "Seeder status: $status2\n";
    $logMsg .= "Seeder output:\n" . \Illuminate\Support\Facades\Artisan::output() . "\n";
    
    $logMsg .= "=== OPERATIONS COMPLETED SUCCESSFULLY ===\n";
} catch (\Exception $e) {
    $logMsg .= "Error during operations: " . $e->getMessage() . "\n";
    $logMsg .= $e->getTraceAsString() . "\n";
}

\Illuminate\Support\Facades\Log::info($logMsg);
echo "Logged results to storage/logs/laravel.log";
