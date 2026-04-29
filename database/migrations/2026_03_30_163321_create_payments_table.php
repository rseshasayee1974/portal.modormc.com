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
        Schema::create('mm_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('ledger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patron_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 12, 2)->default(0);

            $table->enum('transaction_type', ['payment', 'receipt']);

            $table->text('description')->nullable();
            $table->string('reference', 100)->nullable();

            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');

            $table->foreignId('created_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('mm_users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['plant_id', 'status']);
            $table->index(['ledger_id', 'transaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_payments');
    }
};
