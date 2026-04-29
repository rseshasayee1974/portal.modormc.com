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
        Schema::create('mm_quantity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id')->nullable();
            
            $table->decimal('opening_quantity', 17, 2)->default(0);
            $table->decimal('quantity', 17, 2)->default(0); // Current total/available or the change? Assuming total for now
            $table->date('date')->nullable();
            
            $table->boolean('is_warehouse')->default(false);
            $table->tinyInteger('status')->default(1);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable(); // changed updated_at to updated_at standard
            $table->unsignedBigInteger('deleted_by')->nullable();
            
            $table->index(['plant_id', 'product_id']);
            $table->index(['date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_quantity');
    }
};
