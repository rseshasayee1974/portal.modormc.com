<?php
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

echo "Testing environment page paths:\n";
print_r(config('inertia.testing.page_paths'));
print_r(config('inertia.testing.page_extensions'));

$finder = app('inertia.testing.view-finder');
echo "\nFinder class: " . get_class($finder) . "\n";

try {
    var_dump($finder->find('Products/Index'));
} catch (\Exception $e) {
    echo "Error finding Products/Index: " . $e->getMessage() . "\n";
}

// Let's print out what files actually exist in resources/js/Pages/Products
echo "\nFiles in resources/js/Pages/Products:\n";
$dir = dirname(__DIR__) . '/resources/js/Pages/Products';
if (is_dir($dir)) {
    print_r(scandir($dir));
} else {
    echo "Directory does not exist: $dir\n";
}
