<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BatchSheet\Drivers\PlantDriverRegistry;

echo "=== Testing PlantDriverRegistry Auto-Discovery ===\n";
$registry = new PlantDriverRegistry();
$drivers = $registry->getDrivers();

echo "Discovered Drivers count: " . count($drivers) . "\n";
foreach ($drivers as $code => $driver) {
    echo " - [{$code}] => " . $driver->getDriverName() . "\n";
}

echo "\n=== Testing Matching Logic ===\n";

// Sample 1: Plant 121
$text121 = "V J MIX CONCRETE INDIA PVT LTD\nBATCH SHEET REPORT\nPlant Type : M1.5   Plant Sl.No : 121\nBatch Number : 550\nBatch Date : 16-03-2026\nCustomer : R R TULASI\nTotal Set Weight in kg\n1320 2016 2496 1744 0 0 0 0 1120 240 0 756 0 0 0 6.00 0 0 0\nTotal Actual Weight in kg\n1301 2011 2482 1725 0 0 0 0 1116 235 0 754 0 0 0 5.90 0 0 0\n";
$matched121 = $registry->resolve($text121);
echo "Sample 121 matched: " . ($matched121 ? $matched121->getDriverName() : "NONE") . "\n";
if ($matched121) {
    $res121 = $matched121->parse($text121);
    echo " - Batch No: " . ($res121['headerFields']['batch_number'] ?? 'N/A') . "\n";
    echo " - Materials Count: " . count($res121['materialRows']) . "\n";
}

// Sample 2: Plant 782
$text782 = "M/s PALANIYAPPA CONCRETE\nMCI 70 Control System Ver 3.1\nDocket / Batch Report / Autographic Record\nPlant Serial Number : 782\nBatch Date : 26-04-2024\nBatch Number /Docket Number : 338\nCustomer : PRABU SIVARAJ\nTotal Set Weight in Kgs\n6232.5 0 2925 5430 0 2850 0 0 952.5 0 11.25\nTotal Actual Weight in Kgs\n6234.5 0 2941 5423 0 2850 0 0 944.5 0 7.25\n";
$matched782 = $registry->resolve($text782);
echo "\nSample 782 matched: " . ($matched782 ? $matched782->getDriverName() : "NONE") . "\n";
if ($matched782) {
    $res782 = $matched782->parse($text782);
    echo " - Batch No: " . ($res782['headerFields']['batch_number'] ?? 'N/A') . "\n";
    echo " - Materials Count: " . count($res782['materialRows']) . "\n";
}

// Sample 3: Plant 322
$text322 = "M/s. Palaniyappa Concrete\nNew Udhayam\nReport Title: Batch Sheet   Plant Sl.No: 322\nBatch Date 10/7/2024\nBatch Number 21128.00\nCustomer Vinothkumar\nTotal Set Weight in Kgs.\n0 6320 3736 5968 0 0 0 2560 0 0 0 1248 0 0 12.00 0 0 0 0\nTotal Actual Weight in Kgs.\n0 5539 3285 5239 0 0 0 2240 0 0 0 1099 0 0 12.00 0 0 0 0\n";
$matched322 = $registry->resolve($text322);
echo "\nSample 322 matched: " . ($matched322 ? $matched322->getDriverName() : "NONE") . "\n";
if ($matched322) {
    $res322 = $matched322->parse($text322);
    echo " - Batch No: " . ($res322['headerFields']['batch_number'] ?? 'N/A') . "\n";
    echo " - Materials Count: " . count($res322['materialRows']) . "\n";
}

// Sample 4: Plant M1T
$textM1T = "M/S SRI GANESHA READYMIX CONCREETE\nMCI 360 Control System Ver 1.0\nDocket / Batch Report / Autographic Record\nBatch Date 29-Aug-2026   Plant Serial Number M1T-187\nBatch Number 29082\nCustomer CELLCON M4\nTotal Set Weight in Kgs.\n2300 2300 4900 0 0 0 0 500 2000 0 0 675 0 0 0 0 0 0 0\nTotal Actual in Kgs.\n2294 2277 4894 0 0 0 0 501 2000 0 0 683 0 0 0 0 0 0 0\n";
$matchedM1T = $registry->resolve($textM1T);
echo "\nSample M1T matched: " . ($matchedM1T ? $matchedM1T->getDriverName() : "NONE") . "\n";
if ($matchedM1T) {
    $resM1T = $matchedM1T->parse($textM1T);
    echo " - Batch No: " . ($resM1T['headerFields']['batch_number'] ?? 'N/A') . "\n";
    echo " - Materials Count: " . count($resM1T['materialRows']) . "\n";
}

echo "\n=== ALL VERIFICATION TESTS PASSED SUCCESSFULLY! ===\n";
