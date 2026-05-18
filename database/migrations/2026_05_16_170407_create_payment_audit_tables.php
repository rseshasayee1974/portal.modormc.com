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
        // Clean up if they already exist from manual creation or failed runs
        Schema::dropIfExists('mm_payment_transaction_audit');
        Schema::dropIfExists('mm_payment_audit');

        Schema::create('mm_payment_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->json('data');
            $table->string('action')->default('deleted');
            $table->foreignId('action_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mm_payment_transaction_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->unsignedBigInteger('payment_id');
            $table->json('data');
            $table->string('action')->default('deleted');
            $table->foreignId('action_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_payment_transaction_audit');
        Schema::dropIfExists('mm_payment_audit');
    }
};
