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
        Schema::create('mm_images', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->string('ref_no')->nullable();
            $table->text('alt_txt')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_name')->nullable();
            $table->foreignId('plant_id')->nullable()->constrained('mm_plants');
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
        Schema::dropIfExists('mm_images');
    }
};
