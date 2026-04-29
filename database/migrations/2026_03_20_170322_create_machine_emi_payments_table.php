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
        Schema::create('mm_machine_emi_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('machine_loan_id')->constrained('mm_machine_loans')->cascadeOnDelete();

            $table->date('due_date');
            $table->date('paid_date')->nullable();

            $table->decimal('amount', 10, 2);

            $table->string('status'); // pending, paid, overdue

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_machine_emi_payments');
    }
};
