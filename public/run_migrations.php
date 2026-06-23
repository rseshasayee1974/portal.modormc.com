<?php

// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

use Illuminate\Support\Facades\Artisan;

try {
    echo "Running Migrations:\n";
    $exitCode = Artisan::call('migrate', ['--force' => true]);
    echo "Exit Code: $exitCode\n";
    echo Artisan::output() . "\n";
    echo "Migrations completed successfully!\n";
} catch (\Exception $e) {
    echo "Error running migrations: " . $e->getMessage() . "\n";
}
