<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$vite = app(Illuminate\Foundation\Vite::class);

$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

// Test common pages
$pages = [
    'resources/js/Pages/SalesOrders/Index.vue',
    'resources/js/Pages/Quotations/Index.vue',
    'resources/js/Pages/CustomerPOs/Index.vue',
    'resources/js/Pages/Batches/Index.vue',
];

foreach ($pages as $p) {
    try {
        $vite(['resources/js/app.js', $p]);
        echo "Page $p: OK\n";
    } catch (Exception $e) {
        echo "Page $p: FAIL -> " . $e->getMessage() . "\n";
    }
}
