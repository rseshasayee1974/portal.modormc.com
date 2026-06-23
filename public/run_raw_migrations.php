<?php

// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successfully.\n";
}

echo "Starting database schema migration...\n";

DB::statement('SET FOREIGN_KEY_CHECKS = 0');

// 1. Rename tables safely
$tablesToRename = [
    'mm_sales_orders' => 'mm_customer_pos',
    'mm_sales_order_items' => 'mm_customer_po_items',
    'mm_work_orders' => 'mm_sales_orders',
    'mm_work_order_logs' => 'mm_sales_order_logs',
    'mm_work_order_operations' => 'mm_sales_order_operations',
];

foreach ($tablesToRename as $oldName => $newName) {
    try {
        if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
            DB::statement("RENAME TABLE `{$oldName}` TO `{$newName}`");
            echo "Renamed table: {$oldName} -> {$newName}\n";
        } else {
            echo "Skipped rename table {$oldName}\n";
        }
    } catch (\Exception $e) {
        echo "Error renaming table {$oldName}: " . $e->getMessage() . "\n";
    }
}

// 2. Rename columns
// mm_customer_po_items: sales_order_id -> customer_po_id
try {
    DB::statement("ALTER TABLE `mm_customer_po_items` CHANGE `sales_order_id` `customer_po_id` BIGINT UNSIGNED NOT NULL");
    echo "Renamed column in mm_customer_po_items: sales_order_id -> customer_po_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_customer_po_items.sales_order_id: " . $e->getMessage() . "\n";
}

// mm_sales_orders (formerly mm_work_orders): sales_order_id -> customer_po_id
try {
    DB::statement("ALTER TABLE `mm_sales_orders` CHANGE `sales_order_id` `customer_po_id` BIGINT UNSIGNED NULL");
    echo "Renamed column in mm_sales_orders: sales_order_id -> customer_po_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_sales_orders.sales_order_id: " . $e->getMessage() . "\n";
}

// mm_sales_order_logs (formerly mm_work_order_logs): work_order_id -> sales_order_id
try {
    DB::statement("ALTER TABLE `mm_sales_order_logs` CHANGE `work_order_id` `sales_order_id` BIGINT UNSIGNED NOT NULL");
    echo "Renamed column in mm_sales_order_logs: work_order_id -> sales_order_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_sales_order_logs.work_order_id: " . $e->getMessage() . "\n";
}

// mm_sales_order_operations (formerly mm_work_order_operations): work_order_id -> sales_order_id
try {
    DB::statement("ALTER TABLE `mm_sales_order_operations` CHANGE `work_order_id` `sales_order_id` BIGINT UNSIGNED NOT NULL");
    echo "Renamed column in mm_sales_order_operations: work_order_id -> sales_order_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_sales_order_operations.work_order_id: " . $e->getMessage() . "\n";
}

// mm_batches: work_order_id -> sales_order_id
try {
    DB::statement("ALTER TABLE `mm_batches` CHANGE `work_order_id` `sales_order_id` BIGINT UNSIGNED NULL");
    echo "Renamed column in mm_batches: work_order_id -> sales_order_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_batches.work_order_id: " . $e->getMessage() . "\n";
}

// mm_dispatches: sales_order_id -> customer_po_id, then work_order_id -> sales_order_id
try {
    DB::statement("ALTER TABLE `mm_dispatches` CHANGE `sales_order_id` `customer_po_id` BIGINT UNSIGNED NULL");
    echo "Renamed column in mm_dispatches: sales_order_id -> customer_po_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_dispatches.sales_order_id: " . $e->getMessage() . "\n";
}

try {
    DB::statement("ALTER TABLE `mm_dispatches` CHANGE `work_order_id` `sales_order_id` BIGINT UNSIGNED NULL");
    echo "Renamed column in mm_dispatches: work_order_id -> sales_order_id\n";
} catch (\Exception $e) {
    echo "Skipped/Error renaming mm_dispatches.work_order_id: " . $e->getMessage() . "\n";
}

// 3. Drop and recreate constraints to point to new tables / columns
// mm_customer_po_items constraint
try {
    DB::statement("ALTER TABLE `mm_customer_po_items` ADD CONSTRAINT `fk_cust_po_items_po` FOREIGN KEY (`customer_po_id`) REFERENCES `mm_customer_pos` (`id`) ON DELETE CASCADE");
    echo "Recreated foreign key on mm_customer_po_items\n";
} catch (\Exception $e) {
    echo "Note constraint mm_customer_po_items: " . $e->getMessage() . "\n";
}

// mm_sales_orders constraint
try {
    DB::statement("ALTER TABLE `mm_sales_orders` ADD CONSTRAINT `fk_sales_orders_po` FOREIGN KEY (`customer_po_id`) REFERENCES `mm_customer_pos` (`id`) ON DELETE SET NULL");
    echo "Recreated foreign key on mm_sales_orders\n";
} catch (\Exception $e) {
    echo "Note constraint mm_sales_orders: " . $e->getMessage() . "\n";
}

// mm_sales_order_logs constraint
try {
    DB::statement("ALTER TABLE `mm_sales_order_logs` ADD CONSTRAINT `fk_sales_order_logs_so` FOREIGN KEY (`sales_order_id`) REFERENCES `mm_sales_orders` (`id`) ON DELETE CASCADE");
    echo "Recreated foreign key on mm_sales_order_logs\n";
} catch (\Exception $e) {
    echo "Note constraint mm_sales_order_logs: " . $e->getMessage() . "\n";
}

// mm_sales_order_operations constraint
try {
    DB::statement("ALTER TABLE `mm_sales_order_operations` ADD CONSTRAINT `fk_sales_order_ops_so` FOREIGN KEY (`sales_order_id`) REFERENCES `mm_sales_orders` (`id`) ON DELETE CASCADE");
    echo "Recreated foreign key on mm_sales_order_operations\n";
} catch (\Exception $e) {
    echo "Note constraint mm_sales_order_operations: " . $e->getMessage() . "\n";
}

// mm_dispatches constraint
try {
    DB::statement("ALTER TABLE `mm_dispatches` ADD CONSTRAINT `fk_dispatches_po` FOREIGN KEY (`customer_po_id`) REFERENCES `mm_customer_pos` (`id`) ON DELETE CASCADE");
    echo "Recreated foreign key on mm_dispatches\n";
} catch (\Exception $e) {
    echo "Note constraint mm_dispatches: " . $e->getMessage() . "\n";
}

DB::statement('SET FOREIGN_KEY_CHECKS = 1');

// 4. Record migration in migrations table
try {
    $migrationName = '2026_06_20_150000_rename_work_order_tables';
    $exists = DB::table('migrations')->where('migration', $migrationName)->exists();
    if (!$exists) {
        $maxBatch = DB::table('migrations')->max('batch') ?? 0;
        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch' => $maxBatch + 1
        ]);
        echo "Recorded migration in DB migrations table.\n";
    }
} catch (\Exception $e) {
    echo "Error recording migration: " . $e->getMessage() . "\n";
}

echo "Migration completed successfully!\n";
