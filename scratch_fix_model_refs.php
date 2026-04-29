<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dir = __DIR__ . '/app/Models';
$it = new RecursiveDirectoryIterator($dir);

$mappings = [
    "'taxes.id'" => "'mm_taxes.id'",
    "'sites.id'" => "'mm_sites.id'",
    "'product_units.id'" => "'mm_product_units.id'",
    "'personnels.id'" => "'mm_personnels.id'",
    "'patrons.id'" => "'mm_patrons.id'",
    "'machines.id'" => "'mm_machines.id'",
    '"taxes.id"' => '"mm_taxes.id"',
    '"sites.id"' => '"mm_sites.id"',
    '"product_units.id"' => '"mm_product_units.id"',
    '"personnels.id"' => '"mm_personnels.id"',
    '"patrons.id"' => '"mm_patrons.id"',
    '"machines.id"' => '"mm_machines.id"',
];

foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        foreach ($mappings as $old => $new) {
            $content = str_replace($old, $new, $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "FIXED TABLE.COL REFERENCE IN: " . basename($filePath) . "\n";
        }
    }
}
echo "DONE\n";
