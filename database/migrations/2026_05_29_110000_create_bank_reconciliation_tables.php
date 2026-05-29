<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create mm_bank_statement_lines table if not exists
        if (!Schema::hasTable('mm_bank_statement_lines')) {
            Schema::create('mm_bank_statement_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
                $table->foreignId('bank_ledger_id')->constrained('mm_ledgers')->cascadeOnDelete();
                $table->date('transaction_date');
                $table->date('value_date')->nullable();
                $table->text('description');
                $table->string('reference_no', 100)->nullable();
                $table->decimal('debit_amount', 17, 4)->default(0.0000); // withdrawal
                $table->decimal('credit_amount', 17, 4)->default(0.0000); // deposit
                $table->decimal('balance', 17, 4)->nullable();
                $table->unsignedBigInteger('reconciled_line_id')->nullable(); // linked journal line
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Add reconciliation columns to mm_journal_entry_lines table if not exist
        if (!Schema::hasColumn('mm_journal_entry_lines', 'bank_statement_line_id')) {
            Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_statement_line_id')->nullable()->after('is_deleted');
                $table->timestamp('reconciled_at')->nullable()->after('bank_statement_line_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('mm_journal_entry_lines', 'bank_statement_line_id')) {
            Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
                $table->dropColumn(['bank_statement_line_id', 'reconciled_at']);
            });
        }

        Schema::dropIfExists('mm_bank_statement_lines');
    }
};
