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
        Schema::create('mm_mix_designs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('mm_patrons')->cascadeOnDelete();

            $table->string('design_name');
            $table->string('design_code')->nullable();
            $table->string('design_type')->nullable();

            $table->string('unit')->nullable();
            $table->decimal('rate_per_qty', 12, 4)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('plant_id');
            $table->unique(['plant_id', 'partner_id', 'design_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_mix_designs');
    }
};
