<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

Schema::create('mm_permissions', function (Blueprint $t) {
    $t->id(); $t->string('name'); $t->string('guard_name')->default('web');
});

        Schema::create('mm_plants', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('entity_id'); $t->softDeletes();
        });
        Schema::create('mm_ledgers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('plant_id'); $t->string('code'); $t->string('title');
            $t->boolean('status')->default(true); $t->boolean('is_pnl')->default(false); $t->softDeletes();
        });
        Schema::create('mm_patrons', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('plant_id'); $t->string('code'); $t->string('legal_name');
            $t->string('operational_status')->default('active');
            $t->unsignedBigInteger('debit_ledger_id')->nullable(); $t->unsignedBigInteger('credit_ledger_id')->nullable(); $t->softDeletes();
        });
        Schema::create('mm_invoices', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('plant_id'); $t->unsignedBigInteger('partner_id');
            $t->string('invoice_type'); $t->string('status')->nullable(); $t->date('invoice_date'); $t->softDeletes();
        });
        Schema::create('mm_payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('plant_id'); $t->unsignedBigInteger('patron_id')->nullable();
            $t->string('status')->nullable(); $t->date('transaction_date'); $t->softDeletes();
        });
        foreach (['2026_03_20_111013_create_journal_entries_table.php', '2026_03_20_111020_create_journal_entry_lines_table.php', '2026_09_19_120000_create_opening_balance_batches.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        DB::table('mm_plants')->insert([['id' => 1, 'entity_id' => 10], ['id' => 2, 'entity_id' => 20]]);
        foreach ([1 => 'Debtors', 2 => 'Creditors', 3 => 'Bank', 4 => 'Opening clearing', 5 => 'Capital'] as $id => $title) {
            DB::table('mm_ledgers')->insert(['id' => $id, 'plant_id' => 1, 'code' => 'L'.$id, 'title' => $title]);
        }
        DB::table('mm_ledgers')->insert(['id' => 6, 'plant_id' => 2, 'code' => 'OTHER', 'title' => 'Other plant']);
        DB::table('mm_patrons')->insert([
            ['id' => 1, 'plant_id' => 1, 'code' => 'C1', 'legal_name' => 'ABC', 'debit_ledger_id' => 1, 'credit_ledger_id' => 2],
            ['id' => 2, 'plant_id' => 1, 'code' => 'V1', 'legal_name' => 'XYZ', 'debit_ledger_id' => 1, 'credit_ledger_id' => 2],
            ['id' => 3, 'plant_id' => 2, 'code' => 'OTHER', 'legal_name' => 'Other plant', 'debit_ledger_id' => 6, 'credit_ledger_id' => 6],
        ]);
        session(['active_plant_id' => 1]);
