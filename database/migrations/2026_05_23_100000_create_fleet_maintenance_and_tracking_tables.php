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
        // 1. mm_machine_maintanence_request
        Schema::create('mm_machine_maintanence_request', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250);
            $table->text('description');
            $table->foreignId('machine_id')->constrained('mm_machines');
            $table->foreignId('plant_id')->constrained('mm_plants');
            $table->string('max_idle_days', 5)->nullable();
            $table->string('inventory_req_lines', 250);
            $table->tinyInteger('maintanence_type');
            $table->decimal('service_km', 17, 2)->default(0.00);
            $table->tinyInteger('priority');
            $table->unsignedBigInteger('responsible_id'); // references users
            $table->string('repair_location', 100);
            $table->foreignId('repair_vendor_id')->constrained('mm_patrons');
            $table->string('bill_no', 150)->nullable();
            $table->string('order_no', 150)->nullable();
            $table->decimal('discount_amount', 17, 2)->default(0.00);
            $table->decimal('shipping_charges', 10, 2)->default(0.00);
            $table->foreignId('shipping_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
            $table->decimal('adjustment', 10, 2)->default(0.00);
            $table->decimal('rounding_value', 17, 2)->default(0.00);
            $table->string('filename', 250)->nullable();
            $table->tinyInteger('status');
            $table->tinyInteger('bill_status')->default(0);
            $table->dateTime('dead_line');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            
            // Custom audit fields matching DDL exactly
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
        });

        // 2. mm_machine_maintanence_lines
        Schema::create('mm_machine_maintanence_lines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250);
            $table->string('product_quantity', 255);
            $table->dateTime('date_planned');
            $table->foreignId('product_uom')->constrained('mm_product_units');
            $table->foreignId('product_id')->constrained('mm_products');
            $table->text('description')->nullable();
            $table->decimal('price_unit', 17, 2);
            $table->decimal('price_subtotal', 17, 2);
            $table->decimal('price_total', 17, 2);
            $table->foreignId('tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
            $table->decimal('price_tax', 17, 2);
            $table->foreignId('order_id')->constrained('mm_machine_maintanence_request')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('mm_plants');
            $table->tinyInteger('status');
            $table->tinyInteger('priority')->default(0);
            $table->string('invoiced_quantity', 255);
            $table->string('received_quantity', 255);
            $table->decimal('received_price', 17, 2)->nullable();
            $table->foreignId('partner_id')->constrained('mm_patrons');

            // Custom audit fields matching DDL exactly
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
        });

        // 3. mm_machine_service
        Schema::create('mm_machine_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants');
            $table->foreignId('truck_id')->constrained('mm_machines'); // References machine
            $table->integer('service_type');
            $table->decimal('last_service_km', 17, 2)->default(0.00);
            $table->decimal('next_service_km', 17, 2)->default(0.00);
            $table->decimal('current_running_km', 17, 2)->default(0.00);
            $table->string('service_hr_km', 50)->nullable();
            $table->date('service_date')->nullable();
            $table->string('notes', 250)->nullable();
            $table->tinyInteger('status')->default(1);

            // Custom audit fields with soft deletes matching DDL
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->dateTime('deleted')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // 4. mm_machine_tracker
        Schema::create('mm_machine_tracker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants');
            $table->foreignId('machine_id')->constrained('mm_machines');
            $table->string('operation_type', 100)->nullable();
            $table->string('category', 10)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable(); // References users
            $table->dateTime('opening')->nullable();
            $table->dateTime('closing')->nullable();
            $table->decimal('odometer_start', 17, 2)->default(0.00);
            $table->decimal('odometer_end', 17, 2)->default(0.00);
            $table->decimal('hourmeter_start', 17, 2)->default(0.00);
            $table->decimal('hourmeter_end', 17, 2)->default(0.00);
            $table->decimal('eb_start', 17, 2);
            $table->decimal('eb_close', 17, 2);
            $table->decimal('opening_hsd', 17, 2)->default(0.00);
            $table->decimal('closing_hsd', 17, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->decimal('fuel', 17, 2)->default(0.00);
            $table->dateTime('fuel_filled_on')->nullable();
            $table->decimal('last_fuel_filled_km', 17, 2)->default(0.00);
            $table->decimal('fuel_filled_km', 17, 2)->default(0.00);
            $table->string('pump_name', 250)->nullable();
            $table->string('pump_reading', 250)->nullable();
            $table->decimal('amount', 17, 3)->default(0.000);
            $table->tinyInteger('shift')->default(-1);
            
            // Custom audit fields matching DDL exactly
            $table->dateTime('created')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('modified')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->foreignId('company_id')->constrained('mm_entities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_machine_tracker');
        Schema::dropIfExists('mm_machine_service');
        Schema::dropIfExists('mm_machine_maintanence_lines');
        Schema::dropIfExists('mm_machine_maintanence_request');
    }
};
