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
        Schema::create('mm_expense_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->string('name', 250);
            $table->foreignId('ledger_id')->nullable()->constrained('mm_ledgers')->nullOnDelete();
            $table->boolean('status')->default(true);

            // Auditing
            $table->auditColumns();
        });

        Schema::create('mm_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->string('ref_no')->nullable();
            
            $table->foreignId('expense_type_id')->constrained('mm_expense_types')->cascadeOnDelete();
            $table->foreignId('made_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('paid_through')->constrained('mm_ledgers')->cascadeOnDelete();
            $table->decimal('amount', 17, 2)->default(0.00);
            $table->date('date')->nullable();
            
            // Assuming vendors and customers tables exist, otherwise these constraints might fail. Let's use patrons logic if so, but stick to the provided schema.
            // Using patrons table to represent vendors/customers within the unified system just in case, unless explicitly asked otherwise.
            $table->foreignId('vendor_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('mm_machines')->nullOnDelete();

            $table->text('note')->nullable();
            $table->boolean('status')->default(true);

            // Auditing
            $table->auditColumns();
        });

        Schema::create('mm_petty_cash', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->string('ref_no', 50);
            $table->string('prefix', 40)->nullable();
            $table->dateTime('date');

            $table->decimal('opening_balance', 17, 2)->default(0.00);
            $table->decimal('closing_balance', 17, 2)->default(0.00);

            $table->foreignId('paid_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('paid_to')->nullable()->constrained('mm_users')->nullOnDelete();

            $table->boolean('journal_status')->default(false);
            $table->boolean('closed_status')->default(false);

            $table->decimal('request_amount', 17, 2)->nullable();
            $table->string('file', 250)->nullable();
            $table->boolean('status')->default(true);

            // Auditing
            $table->auditColumns();
        });

        Schema::create('mm_petty_cash_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('petty_cash_id')->constrained('mm_petty_cash')->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained('mm_expenses')->cascadeOnDelete();
            
            // Following user's code for patron_id constraint correctly assigned to patrons
            $table->foreignId('patron_id')->nullable()->constrained('mm_patrons')->nullOnDelete();

            $table->decimal('amount', 17, 2);
            $table->decimal('debit', 17, 2)->default(0.00);
            $table->decimal('credit', 17, 2)->default(0.00);
            $table->dateTime('date');

            $table->text('description')->nullable();
            $table->string('remarks', 250)->nullable();

            $table->boolean('status')->default(true);

            // Auditing
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_petty_cash_items');
        Schema::dropIfExists('mm_petty_cash');
        Schema::dropIfExists('mm_expenses');
        Schema::dropIfExists('mm_expense_types');
    }
};
