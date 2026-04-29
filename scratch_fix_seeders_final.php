<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dir = __DIR__ . '/database/seeders';
$it = new RecursiveDirectoryIterator($dir);

foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        // Replace 'entity_id' => $entity->id with 'plant_id' => $entity->plants->first()?->id ?? 1
        $content = str_replace("'entity_id' => \$entity->id", "'plant_id' => \$entity->plants->first()?->id ?? 1", $content);
        $content = str_replace('"entity_id" => $entity->id', '"plant_id" => $entity->plants->first()?->id ?? 1', $content);
        
        // Also handle where clauses in seeders
        $content = str_replace("where('entity_id', \$entity->id)", "where('plant_id', \$entity->plants->first()?->id ?? 1)", $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "FIXED SEEDER: " . basename($filePath) . "\n";
        }
    }
}
echo "DONE\n";
