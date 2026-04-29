<?php

$webPhp = __DIR__ . '/routes/web.php';
$content = file_get_contents($webPhp);

$replacements = [
    'address-types' => 'addresstypes',
    'entity-types' => 'entitytypes',
    'contact-types' => 'contacttypes',
    'bank-account-types' => 'bankaccounttypes',
    'invoice-statuses' => 'invoicestatuses',
    'state-codes' => 'statecodes',
    'subscription-statuses' => 'subscriptionstatuses',
    'select-entity' => 'selectentity',
    'select-plant' => 'selectplant',
    'terms-conditions' => 'termsconditions',
    'purchase-orders' => 'purchaseorders',
    'sales-orders' => 'salesorders',
    'work-orders' => 'workorders',
    'product-units' => 'productunits',
    'product-categories' => 'productcategories',
    'grade-ingredients' => 'gradeingredients',
    'mix-designs' => 'mixdesigns',
    'concrete-grades' => 'concretegrades',
    'machine-types' => 'machinetypes',
    'record-payment' => 'recordpayment',
    'next-code' => 'nextcode',
    'voucher-types' => 'vouchertypes',
    'journal-entries' => 'journalentries',
    'expense-types' => 'expensetypes',
    'petty-cash' => 'pettycash',
    'party-rates' => 'partyrates',
    'verify-otp' => 'verifyotp',
    'resend-otp' => 'resendotp',
    'batch-store' => 'batchstore', // for route names if they appear
];

$newContent = $content;
foreach ($replacements as $old => $new) {
    $newContent = str_replace($old, $new, $newContent);
}

file_put_contents($webPhp, $newContent);
echo "Updated web.php\n";

// Global update for route names in JS/Vue/PHP
$dirs = [
    __DIR__ . '/resources/js',
    __DIR__ . '/app',
    __DIR__ . '/routes',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isDir()) continue;
        if (!in_array($file->getExtension(), ['php', 'vue', 'js'])) continue;

        $content = file_get_contents($file->getPathname());
        $originalContent = $content;

        foreach ($replacements as $old => $new) {
            $content = str_replace($old, $new, $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated " . $file->getPathname() . "\n";
        }
    }
}
