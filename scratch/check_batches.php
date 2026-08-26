<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$batches = \App\Models\Batch::whereIn('batch_no', [138, 139, 140, 141, 142])
    ->orWhereIn('id', [138, 139, 140, 141, 142])
    ->with(['dispatches', 'dispatches.status'])
    ->get();

foreach ($batches as $b) {
    echo "Batch ID: {$b->id} | Batch No: {$b->batch_no} | Batch Size: {$b->batch_size}\n";
    foreach ($b->dispatches as $d) {
        echo "  --> Dispatch ID: {$d->id} | Dispatch No: {$d->dispatch_no} | raw delivered_qty in DB: {$d->getRawOriginal('delivered_qty')} | accessor delivered_qty: {$d->delivered_qty}\n";
        $status = $d->status;
        if ($status && $status->invoice_id) {
            $inv = \App\Models\Invoice::with('items')->find($status->invoice_id);
            if ($inv) {
                echo "      --> Invoice ID: {$inv->id} | Number: {$inv->prefix}{$inv->invoice_number}\n";
                foreach ($inv->items as $item) {
                    echo "          --> Item ID: {$item->id} | Item Qty: {$item->quantity} | Price Unit: {$item->price_unit} | Subtotal: {$item->subtotal}\n";
                }
            }
        } else {
            echo "      --> No Invoice Linked\n";
        }
    }
}
