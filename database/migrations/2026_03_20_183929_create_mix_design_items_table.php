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
        Schema::create('mm_mix_design_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('mix_design_id')->constrained('mm_mix_designs')->cascadeOnDelete();

            $table->foreignId('product_id')->constrained('mm_products')->cascadeOnDelete();
            $table->foreignId('uom_id')->constrained('mm_product_units')->cascadeOnDelete();

            $table->decimal('rate', 12, 4)->nullable();
            $table->decimal('actual_quantity', 12, 4)->nullable();
            $table->decimal('cross_quantity', 12, 4)->nullable();
            $table->decimal('variation_quantity', 12, 4)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['plant_id', 'mix_design_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_mix_design_items');
    }
};
