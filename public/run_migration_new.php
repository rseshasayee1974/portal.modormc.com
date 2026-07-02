<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

try {
    $kernel = $app->make(Kernel::class);
    $output = new BufferedOutput();
    $status = $kernel->handle(
        new ArgvInput(['artisan', 'migrate', '--force']),
        $output
    );
    
    echo "Exit Code: " . $status . "<br>";
    echo "Output: <pre>" . htmlspecialchars($output->fetch()) . "</pre>";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
