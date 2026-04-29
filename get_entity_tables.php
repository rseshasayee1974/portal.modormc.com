<?php

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = Schema::getTables();
$entityTables = [];
foreach($tables as $t) {
    if(Schema::hasColumn($t['name'], 'entity_id')) {
        $entityTables[] = $t['name'];
    }
}
echo implode("\n", $entityTables);
