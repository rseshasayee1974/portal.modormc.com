<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\Schema;

$exists = Schema::hasColumn('mm_customer_pos', 'prefix');
echo "Prefix column exists: " . ($exists ? "YES" : "NO") . "<br>";
$existsRef = Schema::hasColumn('mm_customer_pos', 'reference');
echo "Reference column exists: " . ($existsRef ? "YES" : "NO") . "<br>";
