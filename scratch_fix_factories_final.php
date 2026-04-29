<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dir = __DIR__ . '/database/factories';
$it = new RecursiveDirectoryIterator($dir);

foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        // Replace 'entity_id' => ... with 'plant_id' => ...
        // We'll use a generic replacement that picks the first plant or creates one.
        $content = preg_replace("/'entity_id'\s*=>\s*[^,]+,/", "'plant_id' => \App\Models\Plant::first()?->id ?? \App\Models\Plant::factory(),", $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "FIXED FACTORY: " . basename($filePath) . "\n";
        }
    }
}
echo "DONE\n";
