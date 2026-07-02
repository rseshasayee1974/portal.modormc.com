<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
header('Content-Type: text/plain');

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "APP_ENV: " . app()->environment() . "\n";
echo "Inertia page_paths: " . json_encode(config('inertia.page_paths')) . "\n";
echo "Inertia page_extensions: " . json_encode(config('inertia.page_extensions')) . "\n";
echo "Inertia testing page_paths: " . json_encode(config('inertia.testing.page_paths')) . "\n";
echo "Inertia testing page_extensions: " . json_encode(config('inertia.testing.page_extensions')) . "\n";

try {
    $finder = app('inertia.view-finder');
    echo "Default finder resolved Products/Index to: " . $finder->find('Products/Index') . "\n";
} catch (\Exception $e) {
    echo "Default finder failed: " . $e->getMessage() . "\n";
}

try {
    $testingFinder = app('inertia.testing.view-finder');
    echo "Testing finder resolved Products/Index to: " . $testingFinder->find('Products/Index') . "\n";
} catch (\Exception $e) {
    echo "Testing finder failed: " . $e->getMessage() . "\n";
}
