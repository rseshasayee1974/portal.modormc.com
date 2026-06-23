<?php
$logPath = 'storage/logs/laravel.log';
if (file_exists($logPath)) {
    $lines = explode("\n", file_get_contents($logPath));
    echo implode("\n", array_slice($lines, -150)) . "\n";
}
