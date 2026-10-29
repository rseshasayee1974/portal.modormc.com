<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:12:03 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Accounting integrity triggers require MySQL or SQLite.');
        }
        if (DB::getDriverName() === 'mysql') {
            foreach (['mm_plants', 'mm_ledgers', 'mm_journal_entries', 'mm_journal_entry_lines', 'mm_invoices', 'mm_invoice_items', 'mm_order_taxes', 'mm_payments', 'mm_payment_transactions', 'mm_opening_balance_batches', 'mm_bank_statement_lines'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $engine = DB::table('information_schema.tables')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->value('ENGINE');
                if (strtoupper((string) $engine) !== 'INNODB') {
                    throw new RuntimeException($table.' must use InnoDB before enabling accounting. Back up and convert it in a reviewed maintenance migration.');
                }
            }
        }
        if (DB::table('mm_ledgers')->select('plant_id', 'code')->whereNotNull('code')->groupBy('plant_id', 'code')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate ledger codes exist within a plant. Resolve them with an audited mapping before migrating.');
        }
        Schema::table('mm_ledgers', fn (Blueprint $t) => $t->unique(['plant_id', 'code'], 'acct_ledger_plant_code'));
        Schema::table('mm_ledgers', fn (Blueprint $t) => $t->unique(['plant_id', 'id'], 'acct_ledger_plant_id'));
        Schema::table('mm_journal_entries', fn (Blueprint $t) => $t->unique(['plant_id', 'id'], 'acct_journal_plant_id'));
        Schema::create('mm_accounting_accounts', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('ledger_id')->unique();
            $t->string('code', 50);
            $t->string('type', 20);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('tax_class', 30)->nullable();
            $t->string('control', 20)->nullable();
            $t->boolean('active')->default(true);
            $t->unique(['plant_id', 'code']);
            $t->index(['plant_id', 'type']);
            $t->foreign(['plant_id', 'ledger_id'], 'acct_account_ledger_fk')->references(['plant_id', 'id'])->on('mm_ledgers')->restrictOnDelete();
        });
        Schema::create('mm_accounting_periods', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->string('period', 7);
            $t->boolean('closed')->default(false);
            $t->unique(['plant_id', 'period']);
        });
        Schema::create('mm_accounting_journals', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('journal_id')->unique();
            $t->string('request_key', 100);
            $t->string('digest', 64);
            $t->boolean('sealed')->default(false);
            $t->unsignedBigInteger('reversal_of')->nullable()->unique();
            $t->unique(['plant_id', 'request_key']);
            $t->foreign(['plant_id', 'journal_id'], 'acct_meta_journal_fk')->references(['plant_id', 'id'])->on('mm_journal_entries')->restrictOnDelete();
        });
        Schema::create('mm_accounting_events', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('actor_id');
            $t->string('action', 50);
            $t->longText('payload');
            $t->string('previous', 64);
            $t->string('digest', 64);
            $t->timestamp('created_at');
            $t->index(['plant_id', 'id']);
        });
        Schema::create('mm_accounting_tally', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->string('company', 200);
            $t->unsignedBigInteger('journal_id');
            $t->string('guid', 64);
            $t->longText('xml');
            $t->string('digest', 64);
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('lease', 64)->nullable();
            $t->timestamp('leased_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['plant_id', 'journal_id']);
            $t->unique('guid');
            $t->index(['plant_id', 'status']);
        });
        Schema::create('mm_accounting_settings', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->unsignedBigInteger('plant_id')->unique();
            $t->boolean('tally_enabled')->default(false);
            $t->string('tally_company', 200);
        });
        foreach (['UPDATE', 'DELETE'] as $op) {
            $this->trigger('acct_event_'.strtolower($op), $op, 'mm_accounting_events', '1=1');
            $this->trigger('acct_seal_'.strtolower($op), $op, 'mm_accounting_journals', 'OLD.sealed = 1');
            $condition = 'EXISTS (SELECT 1 FROM mm_accounting_journals a WHERE a.journal_id = OLD.id AND a.sealed = 1)';
            $this->trigger('acct_header_'.strtolower($op), $op, 'mm_journal_entries', $condition);
            $condition = 'EXISTS (SELECT 1 FROM mm_accounting_journals a WHERE a.journal_id = OLD.journal_entry_id AND a.sealed = 1)';
            $this->trigger('acct_line_'.strtolower($op), $op, 'mm_journal_entry_lines', $condition);
        }
        $this->trigger('acct_line_insert', 'INSERT', 'mm_journal_entry_lines', 'EXISTS (SELECT 1 FROM mm_accounting_journals a WHERE a.journal_id = NEW.journal_entry_id AND a.sealed = 1)');
        $invalidSeal = "NEW.sealed = 1 AND (
            NOT EXISTS (SELECT 1 FROM mm_journal_entries j WHERE j.id = NEW.journal_id AND j.plant_id = NEW.plant_id AND UPPER(j.is_status) = 'POSTED' AND j.deleted_at IS NULL AND j.total_debit > 0 AND j.total_debit = j.total_credit
                AND ROUND(j.total_debit * 10000,0) = (SELECT COALESCE(SUM(ROUND(l.debit_amount * 10000,0)),0) FROM mm_journal_entry_lines l WHERE l.journal_entry_id = j.id AND l.deleted_at IS NULL)
                AND ROUND(j.total_credit * 10000,0) = (SELECT COALESCE(SUM(ROUND(l.credit_amount * 10000,0)),0) FROM mm_journal_entry_lines l WHERE l.journal_entry_id = j.id AND l.deleted_at IS NULL))
            OR (SELECT COUNT(*) FROM mm_journal_entry_lines l WHERE l.journal_entry_id = NEW.journal_id AND l.deleted_at IS NULL) < 2
            OR EXISTS (SELECT 1 FROM mm_journal_entry_lines l LEFT JOIN mm_ledgers a ON a.id = l.account_id WHERE l.journal_entry_id = NEW.journal_id AND l.deleted_at IS NULL AND
                (l.plant_id != NEW.plant_id OR a.id IS NULL OR a.plant_id != NEW.plant_id OR a.deleted_at IS NOT NULL OR l.debit_amount < 0 OR l.credit_amount < 0 OR (l.debit_amount > 0 AND l.credit_amount > 0) OR (l.debit_amount = 0 AND l.credit_amount = 0))))";
        $this->trigger('acct_validate_seal', 'UPDATE', 'mm_accounting_journals', $invalidSeal);
    }

    private function trigger(string $name, string $op, string $table, string $condition): void
    {
        DB::unprepared(DB::getDriverName() === 'sqlite'
            ? "CREATE TRIGGER $name BEFORE $op ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable accounting record'); END"
            : "CREATE TRIGGER $name BEFORE $op ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable accounting record'; END IF; END");
    }

    public function down(): void
    {
        if (Schema::hasTable('mm_accounting_events') && DB::table('mm_accounting_events')->exists()) {
            throw new RuntimeException('Accounting history exists. Preserve the tables and restore compatible application code; do not roll back financial evidence.');
        }
        foreach (['acct_validate_seal', 'acct_line_insert', 'acct_event_update', 'acct_event_delete', 'acct_seal_update', 'acct_seal_delete', 'acct_header_update', 'acct_header_delete', 'acct_line_update', 'acct_line_delete'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS $name");
        }
        foreach (['settings', 'tally', 'events', 'journals', 'periods', 'accounts'] as $name) {
            Schema::dropIfExists('mm_accounting_'.$name);
        }
        Schema::table('mm_ledgers', fn (Blueprint $t) => $t->dropUnique('acct_ledger_plant_code'));
        Schema::table('mm_ledgers', fn (Blueprint $t) => $t->dropUnique('acct_ledger_plant_id'));
        Schema::table('mm_journal_entries', fn (Blueprint $t) => $t->dropUnique('acct_journal_plant_id'));
    }
};
