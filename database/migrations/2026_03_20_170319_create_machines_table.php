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
        Schema::create('mm_machines', function (Blueprint $table) {
            $table->id();

            $table->string('registration');
            $table->string('vehicle_model')->nullable();
            $table->year('make_year')->nullable();

            $table->string('engine_no')->nullable();
            $table->string('chassis_no')->nullable();

            $table->string('vehicle_type')->nullable(); // truck, JCB, Excavator, bunker, etc.
            $table->integer('capacity')->nullable();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();

            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_machines');
    }
};
