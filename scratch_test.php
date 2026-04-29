<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payload = [
  "prefix" => "WO",
  "order_no" => null,
  "plant_id" => 2,
  "customer_id" => 3,
  "site_id" => 4,
  "mix_design_id" => 2,
  "total_qty" => 123,
  "produced_qty" => 4,
  "status" => 1,
  "scheduled_start" => "2026-04-22T04:20:00.000Z",
  "scheduled_end" => "2026-04-22T04:20:00.000Z"
];
try {
    $wo = App\Models\WorkOrder::create($payload);
    echo "Created: {$wo->id}\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
