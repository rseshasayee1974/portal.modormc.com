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
        Schema::create('mm_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('mm_entities')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('mm_personnels')->cascadeOnDelete();
            $table->string('license_number', 100);
            $table->date('license_expiry_date')->nullable();
            $table->string('license_type', 100)->nullable();
            $table->string('status', 50)->default('active');
            
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_drivers');
    }
};
