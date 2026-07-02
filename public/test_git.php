<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
header('Content-Type: text/plain');
$projectRoot = dirname(__DIR__);
$cmd = "cd /d " . escapeshellarg($projectRoot) . " && git ls-files resources/js/Pages 2>&1";
echo "Running: $cmd\n\n";
echo shell_exec($cmd);
