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

        // Prefix ANY table name in hasColumn/hasTable if it lacks mm_
        $content = preg_replace('/(Schema::(?:hasColumn|hasTable)\s*\(\s*[\'"])(?!mm_)([a-z0-9_]+)([\'"])/', "$1mm_$2$3", $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "UPDATED SCHEMA CHECKS GENERIC: " . basename($filePath) . "\n";
        }
    }
}
