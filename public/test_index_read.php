<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
header('Content-Type: text/plain');

$indexFile = dirname(__DIR__) . '/.git/index';
if (!file_exists($indexFile)) {
    echo "No git index file found.\n";
    exit;
}

$content = file_get_contents($indexFile);
echo "Length of git index: " . strlen($content) . "\n";

// Let's search for paths containing 'Pages/' and print matches related to Products
preg_match_all('/resources\/js\/Pages\/[^\x00]+/i', $content, $matches);
$found = [];
if (!empty($matches[0])) {
    foreach ($matches[0] as $match) {
        // Clean up binary characters
        $clean = preg_replace('/[^\x20-\x7E]/', '', $match);
        if (stripos($clean, 'Products') !== false) {
            $found[] = $clean;
        }
    }
}

echo "Found paths in git index:\n";
print_r(array_unique($found));
