<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

echo "Inertia Config page_paths:\n";
print_r(config('inertia.page_paths'));
print_r(config('inertia.testing.page_paths'));

echo "\nInertia config ensure_pages_exist:\n";
var_dump(config('inertia.ensure_pages_exist'));
var_dump(config('inertia.testing.ensure_pages_exist'));

echo "\nResolving Products/Index:\n";
try {
    $finder = app('inertia.view-finder');
    var_dump($finder->find('Products/Index'));
} catch (\Exception $e) {
    echo "Error finding Products/Index: " . $e->getMessage() . "\n";
}

echo "\nResolving Products/Categories:\n";
try {
    $finder = app('inertia.view-finder');
    var_dump($finder->find('Products/Categories'));
} catch (\Exception $e) {
    echo "Error finding Products/Categories: " . $e->getMessage() . "\n";
}
