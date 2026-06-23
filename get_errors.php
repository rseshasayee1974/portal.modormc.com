<?php

$logPath = 'storage/logs/laravel.log';
if (!file_exists($logPath)) {
    echo "No log file found.\n";
    exit;
}

$content = file_get_contents($logPath);
$lines = explode("\n", $content);
$errors = [];

foreach ($lines as $line) {
    if (str_contains($line, '.ERROR') || str_contains($line, 'Exception') || str_contains($line, 'ProcessBatchSheetJob')) {
        $errors[] = $line;
    }
}

echo "TOTAL LOG ENTRIES CONTAINING ERROR/JOB: " . count($errors) . "\n";
echo "LAST 20 ERROR LINES:\n";
$lastErrors = array_slice($errors, -50);
foreach ($lastErrors as $err) {
    echo $err . "\n";
}

echo "\nLAST 2000 CHARACTERS OF LOG:\n";
echo substr($content, -2000) . "\n";
