<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$batches = \App\Models\Batch::whereIn('batch_no', [138, 139, 140, 141, 142])
    ->orWhereIn('id', [138, 139, 140, 141, 142])
    ->with('dispatches')
    ->get();

foreach ($batches as $b) {
    $exactSize = (float) $b->batch_size;
    foreach ($b->dispatches as $d) {
        $d->update(['delivered_qty' => $exactSize]);
        echo "Updated Dispatch ID {$d->id} (Batch {$b->batch_no}) delivered_qty to {$exactSize}\n";
    }
}
