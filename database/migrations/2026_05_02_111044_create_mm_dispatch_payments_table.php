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
        Schema::create('mm_dispatch_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('mm_dispatches')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('mm_payment_methods');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_type', ['full', 'partial'])->default('full');

            $table->foreignId('party_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->string('collected_by')->nullable();

            $table->auditColumns();
            $table->index('dispatch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_dispatch_payments');
    }
};
