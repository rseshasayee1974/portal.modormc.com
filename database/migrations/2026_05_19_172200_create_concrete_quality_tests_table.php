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
        Schema::create('mm_concrete_quality_tests', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('mm_batches')->nullOnDelete();
            
            $table->string('test_code')->unique();
            $table->timestamp('test_date')->useCurrent();
            $table->string('tested_by')->nullable();
            
            // Fresh Concrete Testing
            $table->decimal('slump_value', 8, 2)->comment('in mm (e.g. 120)');
            $table->decimal('fresh_temperature', 5, 2)->comment('in °C (e.g. 27.5)');
            $table->decimal('air_content', 4, 2)->comment('in % (e.g. 1.8)');
            $table->decimal('fresh_density', 8, 2)->comment('in kg/m³ (e.g. 2400)');
            
            // Hardened Concrete Testing
            $table->decimal('cube_strength_7_days', 8, 2)->comment('in MPa (compressive strength at 7 days)');
            $table->decimal('cube_strength_28_days', 8, 2)->comment('in MPa (compressive strength at 28 days)');
            $table->decimal('core_test_strength', 8, 2)->nullable()->comment('in MPa (if core extracted)');
            
            // Durability tests
            $table->decimal('water_permeability', 8, 2)->nullable()->comment('in mm');
            $table->decimal('rapid_chloride_permeability', 8, 2)->nullable()->comment('in Coulombs');
            
            // Quality Status
            $table->string('status')->default('pending'); // pending, passed, failed
            $table->text('remarks')->nullable();
            
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_concrete_quality_tests');
    }
};
