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
        Schema::create('mm_product_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();

            // for subcategory (tree structure)
            $table->foreignId('parent_id')->nullable()->constrained('mm_product_categories')->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('description', 255)->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(1);

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate category names per entity
            $table->unique(['plant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_product_categories');
    }
};
