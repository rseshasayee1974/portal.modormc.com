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

        // Simplify plant_id assignment to avoid potential loops
        // Use a closure or 1
        $content = str_replace("'plant_id' => \App\Models\Plant::first()?->id ?? \App\Models\Plant::factory(),", "'plant_id' => 1,", $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "SIMPLIFIED FACTORY: " . basename($filePath) . "\n";
        }
    }
}
echo "DONE\n";
