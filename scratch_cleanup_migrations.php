<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$migrationsDir = __DIR__ . '/database/migrations';
$it = new RecursiveDirectoryIterator($migrationsDir);
foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        // Cleanup: Remove mm_ prefix from obvious column names
        // Matches mm_ followed by common column patterns that are NOT table names
        $content = preg_replace('/([\'"])mm_(status|type|level|name|code|description|value|amount|date|id|at|by|is_[a-z_]+|patron_[a-z_]+|entity_[a-z_]+|plant_[a-z_]+)([\'"])/', '$1$2$3', $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "CLEANED MIGRATION: " . basename($filePath) . "\n";
        }
    }
}
