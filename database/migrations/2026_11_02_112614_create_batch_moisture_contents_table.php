<?php

/*
Author: Cipher
Created: 2026-11-02 11:26:14 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_batch_moisture_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('mm_batches')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('mm_products')->restrictOnDelete();
            // $table->string('material_name');
            $table->decimal('design_moisture_percentage', 6, 3)->nullable();
            $table->decimal('consumption_moisture_percentage', 6, 3)->nullable();
            $table->dateTime('measured_date_time');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['plant_id', 'batch_id', 'product_id'], 'batch_moisture_material_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_batch_moisture_contents');
    }
};
