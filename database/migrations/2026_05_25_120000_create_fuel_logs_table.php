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
        Schema::create('mm_fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('mm_entities')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('mm_machines')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->dateTime('log_date');
            $table->decimal('quantity', 10, 2);
            $table->decimal('rate_per_liter', 10, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('odometer_reading', 17, 2);
            $table->decimal('hourmeter_reading', 17, 2)->nullable();
            $table->string('pump_name', 250)->nullable();
            $table->string('bill_no', 150)->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('attachment_path', 250)->nullable();
            $table->text('notes')->nullable();
            
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_fuel_logs');
    }
};
