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
    'sales_orders', 'purchase_orders', 'sites', 'personnels', 'module', 'custom_settings',
    'mix_design_items', 'concrete_grades', 'concrete_grade_items', 'purchase_order_items', 'contacts', 'addresses'
];

$migrationsDir = __DIR__ . '/database/migrations';
$it = new RecursiveDirectoryIterator($migrationsDir);
foreach (new RecursiveIteratorIterator($it) as $fileInfo) {
    if ($fileInfo->getExtension() === 'php') {
        $filePath = $fileInfo->getPathname();
        $content = file_get_contents($filePath);
        $originalContent = $content;

        foreach ($targetTables as $table) {
            // Match Schema::hasColumn('tablename', ...) or Schema::hasTable('tablename')
            // Match EXACTLY since we are inside a method call
             $content = preg_replace('/(Schema::(?:hasColumn|hasTable)\s*\(\s*[\'"])'.$table.'([\'"])/', "$1mm_{$table}$2", $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "UPDATED SCHEMA CHECKS: " . basename($filePath) . "\n";
        }
    }
}
