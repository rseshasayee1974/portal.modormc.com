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
        Schema::create('mm_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('mm_payments')->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('mm_ledgers')->cascadeOnDelete();
            $table->foreignId('patron_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            
            $table->date('transaction_date');
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            
            $table->string('status', 100)->nullable();

            $table->auditColumns();

            $table->index(['payment_id', 'plant_id']);
            $table->index(['ledger_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_payment_transactions');
    }
};
