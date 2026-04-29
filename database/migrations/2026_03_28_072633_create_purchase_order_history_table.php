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
        Schema::create('mm_purchase_order_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->dateTime('received_date');
            
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('uom_id')->nullable();

            $table->decimal('used_quantity', 17, 2)->default(0); // Cumulative received till this point for the item
            $table->decimal('received_qty', 17, 2)->default(0); // Qty received in this specific transaction
            $table->decimal('unit_price', 17, 2)->default(0);
            $table->decimal('count_quantity', 17, 2)->default(0); // Maybe for pack count or similar

            $table->string('inward_no', 250)->nullable();
            $table->tinyInteger('status')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Indexes
            $table->index(['order_id']);
            $table->index(['plant_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_purchase_order_history');
    }
};
