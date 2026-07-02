<?php

header('Content-Type: text/plain');

$hosts = [
    'smtppro.zoho.com' => [465, 587],
    'smtp.zeptomail.com' => [465, 587],
    'api.zeptomail.com' => [443]
];

echo "=== SMTP PORT DIAGNOSTIC TEST ===\n\n";

foreach ($hosts as $host => $ports) {
    foreach ($ports as $port) {
        echo "Testing connection to {$host}:{$port}... ";
        
        $timeout = 5;
        $errno = 0;
        $errstr = '';
        
        // Use ssl:// prefix for port 465
        $target = ($port === 465 || $port === 443) ? "ssl://{$host}" : $host;
        
        $fp = @fsockopen($target, $port, $errno, $errstr, $timeout);
        
        if ($fp) {
            echo "SUCCESS!\n";
            fclose($fp);
        } else {
            echo "FAILED - Error #{$errno}: {$errstr}\n";
        }
    }
    echo "\n";
}

echo "=== LARAVEL CONFIGURATION CHECK ===\n";
try {
    require __DIR__ . '/../bootstrap/app.php';
    
    $mailer = config('mail.default');
    $host = config("mail.mailers.{$mailer}.host");
    $port = config("mail.mailers.{$mailer}.port");
    $encryption = config("mail.mailers.{$mailer}.encryption");
    
    echo "Default Mailer: {$mailer}\n";
    echo "Host: {$host}\n";
    echo "Port: {$port}\n";
    echo "Encryption: {$encryption}\n";
} catch (\Throwable $e) {
    echo "Could not load Laravel configuration: " . $e->getMessage() . "\n";
}
