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
        Schema::create('mm_pump_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('pump_type'); // Machine ID

            $table->decimal('rate', 10, 2);
            $table->string('rate_type', 100); // flat_rate, or per_uom, etc.
            $table->unsignedBigInteger('uom_id')->nullable();

            $table->string('name', 255)->nullable();
            $table->unsignedBigInteger('site_id')->nullable();

            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('plant_id')->references('id')->on('mm_plants')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('mm_patrons')->cascadeOnDelete();
            $table->foreign('pump_type')->references('id')->on('mm_machines')->cascadeOnDelete();
            $table->foreign('uom_id')->references('id')->on('mm_product_units')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('mm_sites')->nullOnDelete();

            $table->foreign('created_by')->references('id')->on('mm_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('mm_users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('mm_users')->nullOnDelete();

            // Indexes
            $table->index(['plant_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_pump_rates');
    }
};
