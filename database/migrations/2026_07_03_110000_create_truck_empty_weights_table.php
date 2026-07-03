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
        Schema::create('mm_truck_empty_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained('mm_machines')->cascadeOnDelete();
            $table->decimal('empty_weight', 15, 3);
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->timestamps();
            
            $table->index(['truck_id', 'plant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_truck_empty_weights');
    }
};
