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
        Schema::create('mm_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('mm_product_categories')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('mm_product_units')->cascadeOnDelete();
            
            $table->boolean('is_service')->default(false); // 0 for Goods, 1 for Service
            
            $table->unsignedBigInteger('purchase_tax_id')->nullable();
            $table->unsignedBigInteger('sale_tax_id')->nullable();
            
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('sales_price', 15, 2)->default(0);
            $table->decimal('convertsion_quantity', 15, 2)->default(0);
            
            $table->string('title')->index();
            $table->string('alias')->nullable();
            $table->string('material_code')->nullable();
            $table->string('product_type')->nullable();
            $table->string('code')->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_returnable')->default(false);
            $table->boolean('status')->default(1);

            $table->auditColumns();
            
            // Prevent duplicate title per entity
            $table->unique(['plant_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_products');
    }
};
