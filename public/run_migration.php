<?php

$migFile = __DIR__.'/../database/migrations/2026_06_25_122000_remove_currency_id_from_purchase_orders_table.php';
$content = file_get_contents($migFile);
if (strpos($content, 'Schema::hasColumn') === false) {
    echo "DISK FILE HAS NOT BEEN MODIFIED!<br>";
} else {
    echo "DISK FILE IS MODIFIED!<br>";
}

if (function_exists('opcache_invalidate')) {
    $res = opcache_invalidate(realpath($migFile), true);
    echo "opcache_invalidate result: " . ($res ? "true" : "false") . "<br>";
} else {
    echo "opcache_invalidate function does not exist!<br>";
}

if (function_exists('opcache_reset')) {
    $res = opcache_reset();
    echo "opcache_reset result: " . ($res ? "true" : "false") . "<br>";
} else {
    echo "opcache_reset function does not exist!<br>";
}
