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
        Schema::create('mm_party_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('patron_id')->nullable();

            $table->unsignedBigInteger('loading_site')->nullable();
            $table->unsignedBigInteger('unloading_site')->nullable();

            $table->unsignedBigInteger('uom_id')->nullable();
            $table->string('payment_type', 100)->nullable();

            $table->unsignedBigInteger('product_id')->nullable();

            $table->decimal('product_rate', 10, 2)->nullable();
            $table->decimal('transport_rate', 10, 2)->nullable();
            $table->decimal('rate', 10, 2);

            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Setup proper foreign keys explicitly mapping to actual tables
            $table->foreign('plant_id')->references('id')->on('mm_plants')->cascadeOnDelete();
            $table->foreign('patron_id')->references('id')->on('mm_patrons')->nullOnDelete();
            $table->foreign('loading_site')->references('id')->on('mm_sites')->nullOnDelete();
            $table->foreign('unloading_site')->references('id')->on('mm_sites')->nullOnDelete();
            $table->foreign('uom_id')->references('id')->on('mm_product_units')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('mm_products')->nullOnDelete();

            $table->foreign('created_by')->references('id')->on('mm_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('mm_users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('mm_users')->nullOnDelete();

            // Indexes
            $table->index(['plant_id', 'patron_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_party_rates');
    }
};
