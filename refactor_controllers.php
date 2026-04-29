<?php

$controllersDir = __DIR__ . '/app/Http/Controllers';
$files = scandir($controllersDir);

foreach ($files as $file) {
    if (str_starts_with($file, 'Mm') && str_ends_with($file, 'Controller.php')) {
        $oldPath = $controllersDir . '/' . $file;
        $newName = substr($file, 2);
        $newPath = $controllersDir . '/' . $newName;

        $content = file_get_contents($oldPath);
        $oldClassName = substr($file, 0, -4);
        $newClassName = substr($newName, 0, -4);

        $content = str_replace("class $oldClassName", "class $newClassName", $content);
        
        file_put_contents($oldPath, $content);
        rename($oldPath, $newPath);
        echo "Renamed $oldClassName to $newClassName\n";
    }
}
