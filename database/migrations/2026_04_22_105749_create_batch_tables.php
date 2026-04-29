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
        Schema::create('mm_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->integer('batch_no')->nullable();
            $table->decimal('batch_size', 10, 3)->default(1);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mm_batch_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('material_name')->nullable();
            $table->decimal('target_qty', 10, 3)->default(0);
            $table->decimal('actual_qty', 10, 3)->default(0);
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('deviation_quantity', 10, 3)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_tables');
    }
};
