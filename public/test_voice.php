<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

header('Content-Type: application/json');
$logs = \App\Models\VoiceLog::where('status', 'failed')->latest()->take(5)->get(['id', 'error', 'created_at']);
echo json_encode($logs, JSON_PRETTY_PRINT);
