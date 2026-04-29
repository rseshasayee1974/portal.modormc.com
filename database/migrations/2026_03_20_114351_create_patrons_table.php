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
        Schema::create('mm_patrons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();

            $table->string('patron_type', 50)->default('Customer');
            $table->string('legal_name', 200);
            $table->string('email', 150)->nullable();
            $table->string('mobile', 20)->nullable();

            // Ledger
            $table->foreignId('ledger_id')->nullable()
                  ->constrained('mm_ledgers')->nullOnDelete();

            // Status (NEW 🔥)
            $table->string('operational_status', 100)->default('active'); //[  'active',   'paused',    'blocked',   'closed'  ]

            // Tax
            $table->string('pan_no', 20)->nullable();
            $table->string('gstin', 20)->nullable();

            // Flags
            $table->boolean('status')->default(1);     // system active/inactive
            $table->boolean('displayed')->default(1);  // UI visibility

            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_patrons');
    }
};
