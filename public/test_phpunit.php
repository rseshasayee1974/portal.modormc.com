<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
header('Content-Type: text/plain');
$projectRoot = dirname(__DIR__);
$cmd = "cd /d " . escapeshellarg($projectRoot) . " && c:\\wamp64\\bin\\php\\php8.3.14\\php.exe vendor/phpunit/phpunit/phpunit tests/Feature/Http/Controllers/ProductControllerTest.php 2>&1";
echo "Running: $cmd\n\n";
echo shell_exec($cmd);
