<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Personnel;
use App\Models\Plant;

echo "--- Plants ---\n";
foreach (Plant::all() as $plant) {
    echo "Plant ID: {$plant->id} | Code: {$plant->code} | Name: {$plant->name}\n";
}

echo "\n--- Personnel Records ---\n";
foreach (Personnel::all() as $p) {
    echo "ID: {$p->id} | Plant ID: {$p->plant_id} | Employee Code: {$p->employee_code} | Name: {$p->first_name} {$p->last_name}\n";
}
