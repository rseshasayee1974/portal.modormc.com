<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dir = __DIR__ . '/database/migrations';
$it = new RecursiveDirectoryIterator($dir);

foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        // references('mm_id') -> references('id')
        $content = str_replace("references('mm_id')", "references('id')", $content);
        $content = str_replace('references("mm_id")', 'references("id")', $content);
        
        // constrained('mm_id') -> constrained('id') (unlikely but possible)
        $content = str_replace("constrained('mm_id')", "constrained('id')", $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "FIXED id REFERENCE IN: " . basename($filePath) . "\n";
        }
    }
}
echo "DONE\n";
