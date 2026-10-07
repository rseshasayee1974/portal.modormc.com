<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$v = Illuminate\Support\Facades\Validator::make(['document_type' => 'BILL', 'document_source' => 'MANUAL'], [
    'document_type' => 'nullable|in:INVOICE,BILL',
    'document_source' => 'nullable|in:DISPATCH,PURCHASE_STOCKIN,MANUAL'
]);
dump($v->errors()->toArray());
