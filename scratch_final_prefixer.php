<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$migrationsDir = __DIR__ . '/database/migrations';
$it = new RecursiveDirectoryIterator($migrationsDir);

$patterns = [
    '/(Schema::(?:create|table|hasColumn|hasTable|drop|dropIfExists)\s*\(\s*[\'"])(?!mm_|migrations|sessions|cache|jobs|failed_jobs|job_batches)/',
    '/(\->(?:constrained|on|references)\s*\(\s*[\'"])(?!mm_)/',
    '/(\->dropForeign\s*\(\s*[\'"])(?!mm_)/',
];

foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        if (strpos($filePath, 'rename_create_tables_bulk') !== false) continue;
        
        $content = file_get_contents($filePath);
        $originalContent = $content;

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, "$1mm_", $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "FIXED PREFIXES IN: " . basename($filePath) . "\n";
        }
    }
}
