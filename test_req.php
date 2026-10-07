<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \App\Http\Requests\StoreInvoiceRequest::create('/test', 'POST', [
    'partner_id' => 1,
    'account_id' => 1,
    'invoice_type' => 'Bill',
    'invoice_label' => 'Manual',
    'document_type' => 'BILL',
    'document_source' => 'MANUAL',
    'invoice_date' => '2026-10-07',
    'items' => [
        [
            'item_id' => 1,
            'item_name' => 'Test',
            'quantity' => 1,
            'price_unit' => 10,
        ]
    ]
]);
$request->setContainer($app);
// Trigger validation
try {
    $request->validateResolved();
    echo "Validation passed\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Validation failed\n";
    print_r($e->errors());
}
