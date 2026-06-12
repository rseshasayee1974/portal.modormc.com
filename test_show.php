<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'ragul@onemodo.com')->first();
$user->default_plant_id = 2;
$user->save();
auth()->login($user);

$controller = app(\App\Http\Controllers\BatchController::class);
// Mock active plant id session
Session::put('active_plant_id', 2);

$response = $controller->index();
$page = $response->toResponse(request())->original;
$batches = $page['props']['batches'] ?? [];
echo json_encode(count($batches) > 0 ? $batches[0] : null, JSON_PRETTY_PRINT);



