<?php
include 'vendor/autoload.php';
$app = include_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$targetTables = [
    'activity_log', 'address_types', 'bank_account_types', 'contact_types', 'countries', 'currencies',
    'entities', 'entity_addresses', 'entity_bank_accounts', 'entity_contacts', 'entity_invoices',
    'entity_subscriptions', 'entity_taxes', 'entity_types', 'entity_users', 'failed_jobs',
    'health_check_result_history_items', 'invoice_payments', 'invoice_statuses', 'job_batches', 'jobs',
    'model_has_permissions', 'model_has_roles', 'password_reset_tokens', 'payment_gateways',
    'payment_statuses', 'permissions', 'plans', 'role_has_permissions', 'roles', 'sessions',
    'state_codes', 'subscription_statuses', 'telescope_entries', 'telescope_entries_tags',
    'telescope_monitoring', 'users', 'patrons', 'machines', 'taxes', 'products', 'product_categories',
    'product_units', 'mix_designs', 'quotations', 'quotation_items', 'work_orders', 'work_order_items',
    'sales_orders', 'purchase_orders', 'sites', 'personnels', 'module', 'custom_settings'
];

$migrationsDir = __DIR__ . '/database/migrations';
$it = new RecursiveDirectoryIterator($migrationsDir);
foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        foreach ($targetTables as $table) {
            // Match exact table name
            $content = preg_replace('/(?<!mm_)([\'"])'.$table.'([\'"])/', "$1mm_{$table}$2", $content);
            // Match strings STARTING with table name (indexes, foreign keys)
            $content = preg_replace('/(?<!mm_)([\'"])'.$table.'(_[a-z0-9_]+)([\'"])/', "$1mm_{$table}$2$3", $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "UPDATED MIGRATION STRINGS: " . basename($filePath) . "\n";
        }
    }
}
