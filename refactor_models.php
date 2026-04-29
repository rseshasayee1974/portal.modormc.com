<?php

function snake_case($value) {
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
}

function plural($value) {
    // Very basic pluralization for mm tables
    if (str_ends_with($value, 'y')) return substr($value, 0, -1) . 'ies';
    if (str_ends_with($value, 's')) return $value . 'es';
    return $value . 's';
}

$modelsDir = __DIR__ . '/app/Models';
$files = scandir($modelsDir);

$renamedModels = [];

foreach ($files as $file) {
    if (str_starts_with($file, 'Mm') && str_ends_with($file, '.php')) {
        $oldPath = $modelsDir . '/' . $file;
        $newName = substr($file, 2);
        $newPath = $modelsDir . '/' . $newName;

        $content = file_get_contents($oldPath);
        $oldClassName = substr($file, 0, -4);
        $newClassName = substr($newName, 0, -4);

        // Update class definition
        $content = str_replace("class $oldClassName", "class $newClassName", $content);
        
        // Ensure $table is set if it wasn't
        if (!str_contains($content, 'protected $table')) {
            $tableName = 'mm_' . snake_case(plural($newClassName));
            // Insert after class definition
            $content = preg_replace('/class\s+' . preg_quote($newClassName) . '\s+extends\s+Model\s*\{/', "$0\n\tprotected \$table = '$tableName';", $content);
        }

        file_put_contents($oldPath, $content);
        rename($oldPath, $newPath);
        $renamedModels[$oldClassName] = $newClassName;
        echo "Renamed model $oldClassName to $newClassName\n";
    }
}

// Global replace of model names
$dirs = [
    __DIR__ . '/app',
    __DIR__ . '/routes',
    __DIR__ . '/database',
    __DIR__ . '/resources/js',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isDir()) continue;
        if (!in_array($file->getExtension(), ['php', 'vue', 'js'])) continue;

        $content = file_get_contents($file->getPathname());
        $originalContent = $content;

        foreach ($renamedModels as $old => $new) {
            // Use word boundaries for replacement to avoid partial matches
            // for PHP files, we check for class usages
            if ($file->getExtension() === 'php') {
                 // Simple string replace for common patterns
                 $content = str_replace($old . '::', $new . '::', $content);
                 $content = str_replace($old . ' $', $new . ' $', $content);
                 $content = str_replace('use App\\Models\\' . $old, 'use App\\Models\\' . $new, $content);
                 $content = str_replace($old . '.php', $new . '.php', $content);
                 $content = str_replace("'" . $old . "'", "'" . $new . "'", $content);
                 $content = str_replace('class ' . $old, 'class ' . $new, $content); // in case of base classes
            } else {
                 $content = str_replace($old, $new, $content);
            }
        }

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated " . $file->getPathname() . "\n";
        }
    }
}
