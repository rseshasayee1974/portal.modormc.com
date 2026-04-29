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
        Schema::create('mm_concrete_grade_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();

            $table->foreignId('concrete_grade_id')
                ->constrained('mm_concrete_grades')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('mm_products')
                ->cascadeOnDelete();

            $table->decimal('quantity', 12, 4)->nullable();

            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plant_id', 'concrete_grade_id', 'product_id'], 'cg_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_concrete_grade_items');
    }
};
