<?php
define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

try {
    echo "Running database migrations...\n";
    $status = Artisan::call('migrate', ['--force' => true]);
    echo "Exit code: " . $status . "\n";
    echo "Output: \n" . Artisan::output() . "\n";
    echo "Migration completed successfully!\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
