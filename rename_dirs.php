<?php
$pagesDir = __DIR__ . '/resources/js/Pages';
if (is_dir("$pagesDir/SalesOrders") && !is_dir("$pagesDir/CustomerPOs")) {
    rename("$pagesDir/SalesOrders", "$pagesDir/CustomerPOs");
    echo "Renamed SalesOrders to CustomerPOs\n";
} else {
    echo "SalesOrders not found or CustomerPOs already exists\n";
}

if (is_dir("$pagesDir/WorkOrders") && !is_dir("$pagesDir/SalesOrders")) {
    rename("$pagesDir/WorkOrders", "$pagesDir/SalesOrders");
    echo "Renamed WorkOrders to SalesOrders\n";
} else {
    echo "WorkOrders not found or SalesOrders already exists\n";
}
