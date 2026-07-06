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
        Schema::create('mm_stock_exhaust', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('mm_patrons');
            $table->string('name', 250);
            $table->string('bill_number', 150)->nullable();
            $table->date('billed_date');
            $table->tinyInteger('invoice_status')->default(0);
            $table->integer('status');
            $table->date('issued_date');
            $table->foreignId('plant_id')->constrained('mm_plants');
            
            // Custom audit fields
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
        });

        Schema::create('mm_stock_exhaust_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('mm_stock_exhaust')->cascadeOnDelete();
            $table->dateTime('issue_date');
            $table->decimal('quantity_issued', 17, 2)->nullable();
            $table->decimal('no_items_issued', 17, 2);
            $table->string('units', 255)->default('');
            $table->string('issued_to', 255);
            $table->foreignId('vehicle_no')->constrained('mm_machines'); // References machine
            $table->decimal('changed_km', 17, 2)->default(0.00);
            $table->string('notes', 200)->nullable();
            
            // Custom audit fields
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_stock_exhaust_lines');
        Schema::dropIfExists('mm_stock_exhaust');
    }
};