<?php
$phpBinary = PHP_BINARY;
$logPath = __DIR__ . '/test1.log';

$cmd1 = "start \"\" /B cmd /c \"echo hello >> \"{$logPath}\" 2>&1\"";
echo "Trying 1: $cmd1\n";
pclose(popen($cmd1, "r"));
sleep(1);
echo file_exists($logPath) ? "File 1 created\n" : "File 1 failed\n";

$cmd2 = "start /B {$phpBinary} -v >> \"{$logPath}\" 2>&1";
echo "Trying 2: $cmd2\n";
pclose(popen($cmd2, "r"));
sleep(1);
echo file_exists($logPath) ? "File 2 created\n" : "File 2 failed\n";
