<?php

use App\Services\Finance\AccountingWorkspace;
use Illuminate\Contracts\Console\Kernel;

/*
Author: ragul-onemodo
Created: 2026-10-18 21:40:26 Asia/Calcutta (UTC+05:30)
*/

require dirname(__DIR__, 2).'/vendor/autoload.php';

$config = json_decode(getenv('ACCOUNTING_WORKER_CONNECTION') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
if (! preg_match('/^codex_accounting_test_[a-f0-9]{16}$/D', $config['database'] ?? '')) {
    throw new RuntimeException('Concurrency workers only accept disposable accounting test databases.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.accounting_worker' => $config, 'database.default' => 'accounting_worker']);
$service = app(AccountingWorkspace::class);
$entry = $service->journal(1, 10, [
    'request_key' => $argv[1], 'date' => '2026-10-10', 'reference' => 'Concurrent test', 'narration' => 'Concurrent test posting',
    'lines' => [['account_id' => 1, 'debit' => '0.3000', 'credit' => '0'], ['account_id' => 2, 'debit' => '0', 'credit' => '0.3000']],
]);
$service->post(1, 20, $entry->id);
echo json_encode(['id' => $entry->id, 'number' => $entry->voucher_number], JSON_THROW_ON_ERROR);
