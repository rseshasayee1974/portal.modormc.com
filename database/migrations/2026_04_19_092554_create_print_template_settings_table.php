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
        Schema::create('mm_print_template_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mm_module_key')->index(); // purchase_orders, invoices, etc.
            $table->foreignId('print_template_id')->constrained('mm_print_templates');
            $table->unsignedBigInteger('plant_id')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->timestamps();
            
            $table->unique(['mm_module_key', 'plant_id'], 'unique_module_plant_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_print_template_settings');
    }
};
