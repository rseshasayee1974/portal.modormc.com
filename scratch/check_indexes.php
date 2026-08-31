<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$indexes = DB::select("SHOW INDEX FROM mm_personnels");
foreach ($indexes as $idx) {
    echo "Index: {$idx->Key_name} | Column: {$idx->Column_name} | Non_unique: {$idx->Non_unique}\n";
}
