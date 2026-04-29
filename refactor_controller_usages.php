<?php

$dirs = [
    __DIR__ . '/app',
    __DIR__ . '/routes',
    __DIR__ . '/database',
    __DIR__ . '/resources/js', // Added JS because Inertia uses these names sometimes
];

$controllers = [
    'MmAddressTypeController',
    'MmBankAccountTypeController',
    'MmContactTypeController',
    'MmCountryController',
    'MmCurrencyController',
    'MmEntityController',
    'MmEntityTypeController',
    'MmExpenseController',
    'MmExpenseTypeController',
    'MmInvoiceStatusController',
    'MmMachineController',
    'MmMenuController',
    'MmPaymentController',
    'MmPersonnelController',
    'MmPettyCashController',
    'MmPlanController',
    'MmProductCategoryController',
    'MmProductController',
    'MmProductUnitController',
    'MmStateCodeController',
    'MmSubscriptionStatusController',
    'MmTaxController',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isDir()) continue;
        if (!in_array($file->getExtension(), ['php', 'vue', 'js'])) continue;

        $content = file_get_contents($file->getPathname());
        $originalContent = $content;

        foreach ($controllers as $old) {
            $new = substr($old, 2);
            $content = str_replace($old, $new, $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated " . $file->getPathname() . "\n";
        }
    }
}
