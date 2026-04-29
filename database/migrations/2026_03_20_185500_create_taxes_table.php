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
        Schema::create('mm_taxes', function (Blueprint $blueprint) {
            $blueprint->id();

            $blueprint->bigInteger('plant_id')->unsigned();

            $blueprint->string('tax_name', 100);
            $blueprint->enum('tax_type', ['sales', 'purchase', 'other sales', 'other purchase', 'others'])->default('sales');

            $blueprint->enum('tax_group', ['GST', 'CGST', 'SGST', 'IGST', 'TDS', 'OTHER']);

            $blueprint->decimal('tax_rate', 10, 2);
            $blueprint->bigInteger('parent_id')->unsigned()->nullable();

            $blueprint->bigInteger('account_id')->unsigned()->nullable();

            $blueprint->tinyInteger('status')->default(1);

            $blueprint->auditColumns();
            $blueprint->unique(['plant_id', 'tax_name']);

            $blueprint->foreign('parent_id')->references('id')->on('mm_taxes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_taxes');
    }
};
