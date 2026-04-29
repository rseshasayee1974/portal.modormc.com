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
        Schema::create('mm_concrete_grades', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();

            $table->string('name', 100); // M20, M25
            $table->string('concrete_code', 50)->nullable();

            // Display ratio (e.g., 1:1.5:3)
            $table->string('concrete_ratio', 50)->nullable();

            // Structured ratios
            $table->decimal('cement_ratio', 8, 2)->nullable();
            $table->decimal('sand_ratio', 8, 2)->nullable();
            $table->decimal('aggregate_ratio', 8, 2)->nullable();

            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_concrete_grades');
    }
};
