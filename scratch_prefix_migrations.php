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

        // 1. Prefix Schema methods with mm_
        $content = preg_replace_callback(
            '/(Schema::(?:create|table|drop|dropIfExists|dropColumns)\s*\(\s*[\'"])([^\'"]+)([\'"])/',
            function ($m) {
                $tableName = $m[2];
                if (!str_starts_with($tableName, 'mm_')) {
                    return $m[1] . 'mm_' . $tableName . $m[3];
                }
                return $m[0];
            },
            $content
        );

        // 2. Prefix table references in constraints
        // Methods: constrained, on
        $content = preg_replace_callback(
            '/((?:constrained|on)\s*\(\s*[\'"])([^\'"]+)([\'"])/',
            function ($m) {
                $tableName = $m[2];
                if (in_array($tableName, ['id', 'uuid', 'guid', 'web', 'api'])) return $m[0];
                if (str_starts_with($tableName, 'mm_')) return $m[0];
                return $m[1] . 'mm_' . $tableName . $m[3];
            },
            $content
        );
        
        // 3. Prefix EXPLICIT index/foreign key drops (strings containing _unique, _foreign, _idx)
        // Methods: dropUnique, dropForeign, dropIndex, unique, index, foreign
        $content = preg_replace_callback(
            '/((?:dropUnique|dropForeign|dropIndex|unique|index|foreign)\s*\(\s*[\'"])([^\'"]+)([\'"])/',
            function ($m) {
                $string = $m[2];
                // Only prefix if it looks like an auto-generated index name or explicit multi-part name
                if (str_contains($string, '_unique') || str_contains($string, '_foreign') || str_contains($string, '_idx')) {
                     if (!str_starts_with($string, 'mm_')) {
                         return $m[1] . 'mm_' . $string . $m[3];
                     }
                }
                return $m[0];
            },
            $content
        );

        // 4. Remove ->after(...) from Schema::create blocks only
        if (str_contains($content, 'Schema::create')) {
             $content = preg_replace_callback(
                '/Schema::create\s*\([^\{]+\{(?:[^{}]|(?R))*\}/s',
                function ($m) {
                    return preg_replace('/->after\([\'"][^\'"]+[\'"]\)/', '', $m[0]);
                },
                $content
            );
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "UPDATED MIGRATION: " . basename($filePath) . "\n";
        }
    }
}
