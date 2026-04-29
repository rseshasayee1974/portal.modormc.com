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
        Schema::create('mm_sites', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('plant_id');
            $table->string('name', 100);
            $table->string('code')->nullable();

            $table->string('type', 20); 

            $table->boolean('is_restricted')->default(false);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();

            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            $table->unique(['plant_id', 'name', 'type'], 'mm_sites_unique_combo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_sites');
    }
};
