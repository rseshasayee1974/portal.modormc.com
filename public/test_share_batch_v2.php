<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use App\Models\Batch;
use App\Models\PublicDocumentLink;
use Illuminate\Support\Facades\Route;

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

try {
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo "Bootstrapping check: OK\n";

    // Find any batch
    $batch = Batch::first();
    if (!$batch) {
        echo "No batches found in database. Cannot test.\n";
        exit;
    }

    echo "Found Batch ID: " . $batch->id . ", Batch No: " . $batch->batch_no . "\n";

    // Auth mock
    $user = \App\Models\User::first();
    if ($user) {
        auth()->login($user);
        echo "Logged in as User: " . $user->name . "\n";
    }

    // Generate share link using the controller directly
    $controller = new \App\Http\Controllers\InvoiceShareController();
    
    // We mock the Request manually
    $request = new \Illuminate\Http\Request();
    $request->replace([
        'document_type' => 'batch',
        'document_id' => $batch->id,
        'expiry' => '7',
    ]);

    $response = $controller->generateLink($request);
    echo "Generate Link Response Status: " . $response->getStatusCode() . "\n";
    echo "Response JSON: " . $response->getContent() . "\n";

    $data = json_decode($response->getContent(), true);
    if (isset($data['success']) && $data['success']) {
        $url = $data['url'];
        $parts = explode('/', $url);
        $token = end($parts);
        echo "Generated Token: " . $token . "\n";

        // Call viewBatch
        $viewResponse = $controller->viewBatch($token);
        $html = $viewResponse->render();
        echo "View Batch HTML Content Length: " . strlen($html) . " bytes\n";
        
        // Call downloadBatchPDF
        try {
            $pdfResponse = $controller->downloadBatchPDF($token);
            echo "Download Batch PDF Response Status: " . $pdfResponse->getStatusCode() . "\n";
            echo "Download Batch PDF Content Length: " . strlen($pdfResponse->getContent()) . " bytes\n";
        } catch (\Exception $e) {
            echo "PDF generation failed: " . $e->getMessage() . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "CRITICAL EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
