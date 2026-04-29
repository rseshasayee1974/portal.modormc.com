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
        Schema::create('mm_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('prefix')->nullable();
            $table->string('order_no')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->foreignId('mix_design_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();
            $table->decimal('produced_qty', 15, 4)->default(0);
            $table->decimal('total_qty', 15, 4)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('mm_product_units')->nullOnDelete();
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            
            // Audit fields
            $table->tinyInteger('status')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->tinyInteger('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
            
            $table->softDeletes();
        });

        Schema::create('mm_work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('mm_work_orders')->onDelete('cascade');
            $table->foreignId('material_id')->nullable()->constrained('mm_products')->nullOnDelete();
            $table->decimal('required_qty', 15, 4)->default(0);
            $table->decimal('issued_qty', 15, 4)->default(0);
            $table->foreignId('uom_id')->nullable()->constrained('mm_product_units')->nullOnDelete();
            $table->unsignedBigInteger('location_id')->nullable();

            // Audit fields
            $table->tinyInteger('status')->default(1);
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->tinyInteger('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->softDeletes();
        });

        Schema::create('mm_work_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('mm_work_orders')->onDelete('cascade');
            $table->string('operation_name');
            $table->integer('sequence')->default(0);
            $table->integer('duration')->nullable(); // in minutes
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Audit fields
            $table->tinyInteger('status')->default(1);
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->tinyInteger('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->softDeletes();
        });

        Schema::create('mm_work_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('mm_work_orders')->onDelete('cascade');
            $table->tinyInteger('status')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->dateTime('changed_at')->nullable();
            
            // Adding audit fields here too as requested for ALL tables
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->tinyInteger('updated_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_work_order_logs');
        Schema::dropIfExists('mm_work_order_operations');
        Schema::dropIfExists('mm_work_order_items');
        Schema::dropIfExists('mm_work_orders');
    }
};
