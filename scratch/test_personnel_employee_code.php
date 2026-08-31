<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Personnel;
use App\Models\Plant;
use Illuminate\Support\Facades\DB;

echo "=== Testing Plant-Based Employee Code Generation ===\n";

// Test Plant 1
$codePlant1 = Personnel::generateNextEmployeeCode(1);
echo "Plant 1 Next Employee Code: {$codePlant1}\n";

// Test Plant 2
$codePlant2 = Personnel::generateNextEmployeeCode(2);
echo "Plant 2 Next Employee Code: {$codePlant2} (should start at EMP-0001)\n";

// Test Plant 3
$codePlant3 = Personnel::generateNextEmployeeCode(3);
echo "Plant 3 Next Employee Code: {$codePlant3} (should start at EMP-0001)\n";

// Test in a transaction (create & rollback)
DB::transaction(function () {
    $p1 = Personnel::create([
        'plant_id' => 2,
        'entity_id' => 1,
        'first_name' => 'Test Employee 1',
        'last_name' => 'Plant 2',
        'employment_type' => 'permanent',
        'status' => 'active',
    ]);
    echo "Created Plant 2 Employee #1: ID={$p1->id}, Code={$p1->employee_code}\n";

    $p2 = Personnel::create([
        'plant_id' => 2,
        'entity_id' => 1,
        'first_name' => 'Test Employee 2',
        'last_name' => 'Plant 2',
        'employment_type' => 'permanent',
        'status' => 'active',
    ]);
    echo "Created Plant 2 Employee #2: ID={$p2->id}, Code={$p2->employee_code}\n";

    // Test Plant 3 creation
    $p3 = Personnel::create([
        'plant_id' => 3,
        'entity_id' => 1,
        'first_name' => 'Test Employee 1',
        'last_name' => 'Plant 3',
        'employment_type' => 'permanent',
        'status' => 'active',
    ]);
    echo "Created Plant 3 Employee #1: ID={$p3->id}, Code={$p3->employee_code}\n";

    // Rollback so test records are not persisted
    throw new \Exception('Rollback test transaction');
});
