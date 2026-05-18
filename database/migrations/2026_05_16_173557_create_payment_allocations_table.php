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
        Schema::create('mm_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('mm_payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('mm_invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->auditColumns(); // Includes created_at, updated_at, etc.
            
            $table->index(['payment_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_payment_allocations');
    }
};
