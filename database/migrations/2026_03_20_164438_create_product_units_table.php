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
        Schema::create('mm_product_units', function (Blueprint $table) {
            $table->id();

            $table->string('unit_type'); // Sales, Purchase, Production, etc.
            $table->string('unit_name', 50); // Kilogram, Piece, Liter
            $table->string('unit_code', 10)->nullable(); // kg, pcs, ltr

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicates
            $table->unique(['unit_name', 'unit_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_product_units');
    }
};
