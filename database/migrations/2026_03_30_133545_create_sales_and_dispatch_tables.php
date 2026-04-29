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
        Schema::create('mm_quotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->string('prefix')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('patron_id');
            $table->unsignedBigInteger('site_id')->nullable();

            $table->date('quote_date');
            $table->dateTime('validity_date')->nullable();

            $table->decimal('amount_untaxed', 15, 2)->default(0);
            $table->decimal('amount_tax', 15, 2)->default(0);
            $table->decimal('amount_total', 15, 2)->default(0);

            $table->tinyInteger('status')->default(0);

            $table->auditColumns();

            $table->foreign('patron_id')->references('id')->on('mm_patrons');
            $table->foreign('plant_id')->references('id')->on('mm_plants');
            $table->foreign('site_id')->references('id')->on('mm_sites');
        });

        Schema::create('mm_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id');
            $table->unsignedBigInteger('product_id');

            $table->decimal('quantity', 17, 2);
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('rate', 17, 2);

            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_amount', 17, 2)->default(0);
            $table->decimal('untaxed_amount', 17, 2)->default(0);
            $table->decimal('amount_total', 17, 2)->default(0);

            $table->auditColumns();

            $table->foreign('quotation_id')->references('id')->on('mm_quotations')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('mm_products');
        });

        Schema::create('mm_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('patron_id');
            $table->unsignedBigInteger('site_id');

            $table->date('order_date');
            $table->tinyInteger('status')->default(0);

            $table->auditColumns();

            $table->foreign('plant_id')->references('id')->on('mm_plants');
            $table->foreign('quotation_id')->references('id')->on('mm_quotations');
            $table->foreign('patron_id')->references('id')->on('mm_patrons');
            $table->foreign('site_id')->references('id')->on('mm_sites');
        });

        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');

            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('plant_id');

            $table->dateTime('dispatch_time');
            $table->dateTime('delivery_time')->nullable();

            $table->tinyInteger('status')->default(0);

            $table->auditColumns();

            $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('mm_machines'); // Vehicles are in machines typically 
            $table->foreign('plant_id')->references('id')->on('mm_plants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_dispatches');
        Schema::dropIfExists('mm_sales_orders');
        Schema::dropIfExists('mm_quotation_items');
        Schema::dropIfExists('mm_quotations');
    }
};
