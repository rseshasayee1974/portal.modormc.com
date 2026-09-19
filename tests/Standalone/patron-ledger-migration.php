<?php

// Run with: php tests/Standalone/patron-ledger-migration.php
// Uses an isolated in-memory database; never touches configured application data.
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
Illuminate\Support\Facades\DB::purge('sqlite');

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Invoice;
use App\Models\Patron;
use App\Models\JournalEntry;

function check($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}

Schema::create('mm_ledgers', function (Blueprint $table) {
    $table->id();
});
Schema::create('mm_patrons', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('plant_id')->default(1);
    $table->string('operational_status')->default('active');
    $table->softDeletes();
    $table->foreignId('ledger_id')->nullable()->constrained('mm_ledgers');
    $table->foreignId('debit_ledger_id')->nullable()->constrained('mm_ledgers');
    $table->foreignId('credit_ledger_id')->nullable()->constrained('mm_ledgers');
});
DB::table('mm_ledgers')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
DB::table('mm_patrons')->insert([
    ['id' => 1, 'ledger_id' => 1, 'debit_ledger_id' => null, 'credit_ledger_id' => null],
    ['id' => 2, 'ledger_id' => 1, 'debit_ledger_id' => 2, 'credit_ledger_id' => 3],
    ['id' => 3, 'ledger_id' => null, 'debit_ledger_id' => null, 'credit_ledger_id' => null],
]);
$migration = require __DIR__ . '/../../database/migrations/2026_09_19_130000_remove_legacy_ledger_from_patrons_table.php';
$migration->up();
check(false, Schema::hasColumn('mm_patrons', 'ledger_id'), 'Legacy column removed');
$rows = DB::table('mm_patrons')->orderBy('id')->get();
check(1, $rows[0]->debit_ledger_id, 'Debit legacy mapping preserved');
check(1, $rows[0]->credit_ledger_id, 'Credit legacy mapping preserved');
check(2, $rows[1]->debit_ledger_id, 'Explicit debit mapping preserved');
check(3, $rows[1]->credit_ledger_id, 'Explicit credit mapping preserved');
check(null, $rows[2]->debit_ledger_id, 'Unmapped patron remains nullable');

check(2, JournalEntry::resolvePatronLedgerId(1, 2, 100, 0), 'Journal debit selection');
check(3, JournalEntry::resolvePatronLedgerId(1, 2, 0, 100), 'Journal credit selection');
$patron = Patron::findOrFail(2);
foreach (['sales' => 2, 'bill' => 3, 'credit_note' => 2, 'receipt' => 2, 'payment' => 3] as $type => $expected) {
    $invoice = new Invoice(['invoice_type' => $type]);
    $invoice->setRelation('partner', $patron);
    check($expected, $invoice->getPartnerLedgerId(), $type . ' account selection');
}

$migration->down();
check(true, Schema::hasColumn('mm_patrons', 'ledger_id'), 'Rollback restores column');
check(1, DB::table('mm_patrons')->where('id', 1)->value('ledger_id'), 'Rollback mapping');
echo "Patron ledger migration and account selection checks passed.\n";
