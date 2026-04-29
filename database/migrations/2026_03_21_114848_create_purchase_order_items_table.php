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
        Schema::create('mm_purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('mm_purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('mm_products');
            $table->foreignId('product_uom')->constrained('mm_product_units');
            $table->foreignId('tax_id')->nullable()->constrained('mm_taxes');

            $table->decimal('product_quantity', 17, 2);
            $table->decimal('unit_price', 17, 2)->default(0);

            $table->text('description')->nullable();

            $table->decimal('price_subtotal', 17, 2)->default(0);
            $table->decimal('price_total', 17, 2)->default(0);
            $table->decimal('price_tax', 17, 2)->default(0);

            $table->string('hsn_code', 100)->nullable();

            $table->string('discount_type', 100)->nullable(); // percentage, fixed
            $table->decimal('discount_amount', 17, 2)->default(0);

            $table->decimal('invoiced_quantity', 17, 2)->default(0);
            $table->decimal('received_quantity', 17, 2)->default(0);

            $table->tinyInteger('status')->default(1);

            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_purchase_order_items');
    }
};
