<?php
define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain');

echo "=== USER STORE FAILURE TRACES ===\n";
try {
    $traces = DB::table('tr_traces')
        ->where('name', 'like', '%users%')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
        
    foreach ($traces as $trace) {
        echo "Trace ID: " . $trace->id . "\n";
        echo "Name: " . $trace->name . "\n";
        echo "Status: " . $trace->status . "\n";
        echo "Created At: " . $trace->created_at . "\n";
        
        $steps = DB::table('tr_trace_steps')
            ->where('trace_id', $trace->id)
            ->get();
            
        foreach ($steps as $step) {
            echo "  Step Label: " . $step->label . "\n";
            echo "  Type: " . $step->type . "\n";
            if ($step->response_payload) {
                $payload = json_decode($step->response_payload, true);
                if (is_array($payload)) {
                    $body = isset($payload['body']) ? $payload['body'] : $payload;
                    if (is_array($body)) {
                        echo "    Message: " . ($body['message'] ?? '') . "\n";
                        echo "    Exception: " . ($body['exception'] ?? '') . "\n";
                        echo "    File: " . ($body['file'] ?? '') . ":" . ($body['line'] ?? '') . "\n";
                        if (isset($body['errors'])) {
                            echo "    Validation Errors: " . json_encode($body['errors']) . "\n";
                        }
                    } else {
                        echo "    Body Str: " . substr($body, 0, 500) . "\n";
                    }
                } else {
                    echo "    Payload Str: " . substr($step->response_payload, 0, 500) . "\n";
                }
            }
        }
        echo "=====================================\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
