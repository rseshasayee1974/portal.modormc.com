<?php

$modelsPath = __DIR__.'/app/Models/*.php';
$files = glob($modelsPath);

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);

    if (strpos($content, 'class ') !== false && !strpos($content, 'use HasFactory;')) {
        // Add use statement
        if (strpos($content, 'use Illuminate\Database\Eloquent\Factories\HasFactory;') === false) {
            $content = preg_replace(
                '/(namespace\s+App(?:\\\\)Models;)/',
                "$1\n\nuse Illuminate\Database\Eloquent\Factories\HasFactory;",
                $content
            );
        }

        // Add use trait
        $content = preg_replace(
            '/(class\s+[a-zA-Z0-9_]+\s+extends\s+[a-zA-Z0-9_\\\\]+\s*\{)/',
            "$1\n\tuse HasFactory;\n",
            $content,
            1
        );

        file_put_contents($filePath, $content);
        echo "Added HasFactory to " . basename($filePath) . "\n";
    }
}
