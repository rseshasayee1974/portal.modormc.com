<?php
$phpBinary = 'C:\wamp64\bin\php\php8.3.14\php.exe';
$artisanPath = __DIR__ . '\artisan';
$logPath = __DIR__ . '\storage\logs\bulk-document-export.log';

$cmd = "start /B {$phpBinary} {$artisanPath} reports:export-bulk-documents fake_status 1 eyJ0eXBlIjoiaW52b2ljZSJ9 >> {$logPath} 2>&1";
echo "Executing: $cmd\n";
pclose(popen($cmd, 'r'));
echo "Started\n";
