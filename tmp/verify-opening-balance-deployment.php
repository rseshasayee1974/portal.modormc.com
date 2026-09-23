<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$originalDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();
$sql = file_get_contents(__DIR__.'/../database/sql/2026_09_22_latest_two_commits.sql');
$sql = preg_replace('/^USE `v4_modomines1`;$/m', '-- Test database is selected explicitly below.', $sql, 1, $databaseSelectionCount);
if ($databaseSelectionCount !== 1 || preg_match('/^\s*USE\b/im', $sql)) {
    throw new RuntimeException('Unexpected database selection in deployment script. Refusing to run tests.');
}
$withoutComments = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $withoutComments)));
$coreTables = ['mm_permissions', 'mm_roles', 'mm_role_has_permissions', 'mm_menus', 'migrations'];
$definitions = [];
foreach ($coreTables as $table) {
    $definitions[$table] = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC)['Create Table'];
}
$checks = 0;
$check = function (bool $condition, string $message) use (&$checks) {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$executeScript = function () use ($pdo, $statements) {
    foreach ($statements as $statement) {
        try {
            $result = $pdo->query($statement);
            $result->closeCursor();
        } catch (Throwable $e) {
            throw new RuntimeException(substr($statement, 0, 150).': '.$e->getMessage(), 0, $e);
        }
    }
};

try {
    foreach (['missing_base', 'legacy_base'] as $scenario) {
        $scratch = 'codex_ob_sql_'.bin2hex(random_bytes(8));
        if (!preg_match('/^codex_ob_sql_[a-f0-9]{16}$/', $scratch) || $scratch === $originalDatabase) {
            throw new RuntimeException('Invalid scratch database name.');
        }
        $pdo->exec('CREATE DATABASE `'.$scratch.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $pdo->exec('USE `'.$scratch.'`');
            foreach ($definitions as $definition) $pdo->exec($definition);
            $pdo->exec("INSERT INTO mm_roles (id, code, name, guard_name) VALUES
                (1,'SAAS_OWNER','Saas Owner','web'), (2,'PLATFORM_ADMIN','Platform Admin','web'),
                (3,'SUPER_ADMIN','Super Admin','web'), (4,'ADMINISTRATOR','Administrator','web'),
                (5,'OPERATOR','Operator','web')");
            $pdo->exec("INSERT INTO mm_permissions (id,name,guard_name,module) VALUES (1,'JOURNAL_ENTRY.VIEW','web','JOURNAL_ENTRY')");
            $pdo->exec('INSERT INTO mm_role_has_permissions (permission_id,role_id) VALUES (1,5)');
            $pdo->exec("INSERT INTO mm_menus (menutype,title,alias,link,parent_id) VALUES (2,'Journal Entries','journal-entries','finance/journalentries',9)");
            $pdo->exec("INSERT INTO migrations (migration,batch) VALUES ('existing_application_migration',7)");
            $legacy = null;
            if ($scenario === 'legacy_base') {
                preg_match('/CREATE TABLE IF NOT EXISTS `mm_opening_balance_batches`.*?;/s', $sql, $match);
                $legacyDdl = preg_replace('/^    `(patron_id|account_id|active_key|replaces_id|deleted_at|deleted_by)`[^\n]*\n/m', '', $match[0]);
                $legacyDdl = str_replace('UNIQUE KEY `opening_balance_active_target` (`plant_id`, `active_key`)',
                    'UNIQUE KEY `mm_opening_balance_batches_plant_id_unique` (`plant_id`)', $legacyDdl);
                $pdo->exec($legacyDdl);
                $pdo->exec("INSERT INTO mm_opening_balance_batches (id,plant_id,cutover_date,notes,`lines`,status,version)
                    VALUES (91,3,'2026-04-01','Preserve existing balance','[]','POSTED',2)");
                $legacy = $pdo->query('SELECT * FROM mm_opening_balance_batches WHERE id=91')->fetch(PDO::FETCH_ASSOC);
            }

            $executeScript();
            $check((int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME='mm_opening_balance_batches' AND COLUMN_NAME IN ('patron_id','account_id','active_key','replaces_id','deleted_at','deleted_by')")->fetchColumn() === 6, 'Six new columns missing');
            $check((int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME='mm_opening_balance_batches' AND INDEX_NAME='mm_opening_balance_batches_plant_id_unique'")->fetchColumn() === 0, 'Old plant constraint remains');
            $check((int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME='mm_opening_balance_batches' AND INDEX_NAME='opening_balance_active_target' AND NON_UNIQUE=0")->fetchColumn() === 2, 'New unique target constraint missing');
            if ($legacy) {
                $after = $pdo->query('SELECT * FROM mm_opening_balance_batches WHERE id=91')->fetch(PDO::FETCH_ASSOC);
                $check(array_intersect_key($after, $legacy) === $legacy, 'Existing balance changed');
                $check($after['active_key'] === null && $after['deleted_at'] === null, 'Legacy balance prematurely converted or removed');
            }
            $pdo->exec("INSERT INTO mm_opening_balance_batches (plant_id,cutover_date,`lines`,active_key) VALUES
                (3,'2026-04-01','[]','ledger:8'), (3,'2026-04-01','[]','ledger:9')");
            try {
                $pdo->exec("INSERT INTO mm_opening_balance_batches (plant_id,cutover_date,`lines`,active_key) VALUES (3,'2026-04-01','[]','ledger:8')");
                throw new RuntimeException('Duplicate active target accepted');
            } catch (PDOException $e) { $check($e->getCode() === '23000', 'Unexpected duplicate-target error'); }
            $pdo->exec("INSERT INTO mm_opening_balance_audit_logs (plant_id,batch_id,version,action,user_id,after_values,created_at)
                VALUES (3,91,1,'CREATE',1,'{}',CURRENT_TIMESTAMP)");
            try {
                $pdo->exec("INSERT INTO mm_opening_balance_audit_logs (plant_id,batch_id,version,action,user_id,after_values,created_at)
                    VALUES (3,91,1,'CREATE',1,'{}',CURRENT_TIMESTAMP)");
                throw new RuntimeException('Duplicate audit version accepted');
            } catch (PDOException $e) { $check($e->getCode() === '23000', 'Unexpected duplicate-audit error'); }

            $executeScript();
            $check((int) $pdo->query("SELECT COUNT(*) FROM mm_permissions WHERE module='OPENING_BALANCE'")->fetchColumn() === 5, 'Incorrect permission count after rerun');
            $check((int) $pdo->query('SELECT COUNT(*) FROM mm_role_has_permissions WHERE role_id IN (1,2,3,4)')->fetchColumn() === 20, 'Incorrect administrator grants');
            $check((int) $pdo->query('SELECT COUNT(*) FROM mm_role_has_permissions WHERE role_id=5')->fetchColumn() === 1, 'Regular role grants changed');
            $check((int) $pdo->query("SELECT COUNT(*) FROM mm_menus WHERE alias='opening-balances' AND permission_name='OPENING_BALANCE.VIEW' AND parent_id=9")->fetchColumn() === 1, 'Menu missing or duplicated');
            $check((int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === 6, 'Migration registry missing or duplicated');
            $check((int) $pdo->query('SELECT COUNT(*) FROM mm_opening_balance_audit_logs')->fetchColumn() === 1, 'Rerun changed audit history');
            $check((int) $pdo->query('SELECT COUNT(*) FROM mm_opening_balance_batches')->fetchColumn() === ($legacy ? 3 : 2), 'Rerun changed balances');
            echo $scenario.": SQL import and rerun passed.\n";
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pdo->exec('USE `'.str_replace('`', '``', $originalDatabase).'`');
            $pdo->exec('DROP DATABASE `'.$scratch.'`');
        }
    }
    echo "Passed {$checks} SQL deployment checks in isolated scratch databases. Application tables were not modified.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
}
