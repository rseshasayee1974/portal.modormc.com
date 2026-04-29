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
        Schema::create('mm_custom_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('plant_id');
            $table->integer('mm_module_id')->nullable();
            $table->string('mm_module_name', 100);   // purchase, sales, invoice
            $table->text('settings');

            $table->timestamps();

            $table->unique(['plant_id', 'mm_module_name'], 'unique_module_plant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_custom_settings');
    }
};
