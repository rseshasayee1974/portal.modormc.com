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
        Schema::create('mm_machine_loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('machine_id')->constrained('mm_machines')->cascadeOnDelete();

            $table->decimal('loan_amount', 12, 2);
            $table->decimal('emi_amount', 10, 2);

            $table->integer('tenure_months');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_machine_loans');
    }
};
