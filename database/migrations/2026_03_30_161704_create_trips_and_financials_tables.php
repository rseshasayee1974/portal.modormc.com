<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supporting table: Transports (if missing)
       

        // Supporting table: Payment Methods
        if (!Schema::hasTable('mm_payment_methods')) {
            Schema::create('mm_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Cash, GPay, Card, Bank Transfer
                $table->timestamps();
            });
        }

        Schema::create('mm_trips', function (Blueprint $table) {
            $table->id();
            $table->enum('trip_type', ['inbound', 'outbound'])->default('outbound');
            $table->string('reference_id')->nullable();

            // Relations
            $table->foreignId('truck_id')->nullable()->constrained('mm_machines')->nullOnDelete();
            $table->string('truck_model')->nullable();

            $table->foreignId('party_id')->constrained('mm_patrons'); // Buyer/Sales
            $table->foreignId('vendor_id')->nullable()->constrained('mm_patrons')->nullOnDelete(); // Source/Supplier
            $table->foreignId('transport_id')->nullable()->constrained('mm_patrons')->nullOnDelete();

            $table->foreignId('load_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
            $table->foreignId('unload_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();

            $table->foreignId('product_id')->nullable()->constrained('mm_products')->nullOnDelete();

            // Personnel
            $table->foreignId('driver_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->foreignId('cleaner_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->foreignId('maistry_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->foreignId('loader_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('mm_personnels')->nullOnDelete();

            $table->enum('payment_mode', ['cash', 'credit'])->default('credit');

            $table->foreignId('plant_id')->constrained('mm_plants'); // Maps to user's "company_id" (Branch)
            $table->foreignId('entity_id')->constrained('mm_entities'); // Maps to user's "entity_id" (Tenant)

            $table->auditColumns();

            $table->index(['party_id', 'vendor_id']);
        });

        Schema::create('mm_trip_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('mm_trips')->cascadeOnDelete();

            // Load side
            $table->decimal('empty_weight_load', 12, 2)->nullable();
            $table->decimal('loaded_weight_load', 12, 2)->default(0);
            $table->string('empty_weight_image')->nullable();
            $table->string('loaded_weight_image')->nullable();
            $table->timestamp('empty_weight_time')->nullable();
            $table->timestamp('loaded_weight_time')->nullable();

            // Unload side
            $table->decimal('empty_weight_unload', 12, 2)->nullable();
            $table->decimal('loaded_weight_unload', 12, 2)->nullable();

            $table->decimal('round_off', 5, 2)->default(0);
            $table->auditColumns();
            $table->index('trip_id');
        });

        Schema::create('mm_trip_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('mm_trips')->cascadeOnDelete();

            // Product pricing
            $table->integer('product_units')->nullable();
            $table->decimal('product_amount', 12, 2)->default(0);
            $table->foreignId('product_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
            $table->decimal('product_tax_amount', 12, 2)->default(0);

            // Revisions
            $table->decimal('updated_product_amount', 12, 2)->default(0);
            $table->foreignId('updated_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
            $table->decimal('updated_tax_rate', 8, 2)->default(0);
            $table->timestamp('updated_at_time')->nullable();
            $table->string('update_reference')->nullable();

            // Unload pricing
            $table->integer('unload_units')->nullable();
            $table->decimal('unload_total_amount', 12, 2)->nullable();

            // Transport pricing
            $table->decimal('transport_rate', 12, 2)->default(0);
            $table->integer('transport_unit')->nullable();
            $table->foreignId('transport_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
            $table->decimal('transport_tax_rate', 8, 2)->default(0);
            $table->string('transport_reference')->nullable();

            // Surcharges & Reductions
            $table->decimal('pass_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('transport_expenses', 12, 2)->default(0);
            $table->decimal('cost_of_product', 12, 2)->nullable();
            $table->decimal('round_off', 5, 2)->default(0);

            $table->auditColumns();
            $table->index('trip_id');
        });

        Schema::create('mm_trip_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained('mm_trips')->cascadeOnDelete();

            $table->tinyInteger('trip_status')->default(0); // 0=Draft, 1=In-Transit, 2= Arrived, 3=Unloaded, 4=Completed
            $table->boolean('is_closed')->default(false);

            $table->tinyInteger('invoice_status')->default(0);
            $table->tinyInteger('transport_bill_status')->default(0);
            $table->tinyInteger('purchase_bill_status')->default(0);

            $table->tinyInteger('driver_salary_status')->default(0);
            $table->tinyInteger('cleaner_salary_status')->default(0);

            $table->boolean('is_load_tax_inclusive')->default(false);
            $table->boolean('is_unload_tax_inclusive')->default(false);

            // Invoice Traceability
            $table->unsignedBigInteger('invoice_id')->nullable(); // link to system invoice
            $table->date('invoice_date')->nullable();
            $table->string('invoice_number')->nullable();

            $table->date('purchase_date')->nullable();
            $table->string('purchase_invoice_number')->nullable();

            $table->date('transport_date')->nullable();
            $table->string('transport_invoice_number')->nullable();

            $table->decimal('transport_km', 10, 2)->default(0);

            $table->auditColumns();
        });

        Schema::create('mm_trip_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('mm_trips')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('mm_payment_methods');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_type', ['full', 'partial'])->default('full');

            $table->foreignId('party_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('collected_by')->nullable();

            $table->auditColumns();
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_trip_payments');
        Schema::dropIfExists('mm_trip_statuses');
        Schema::dropIfExists('mm_trip_financials');
        Schema::dropIfExists('mm_trip_weights');
        Schema::dropIfExists('mm_trips');
        Schema::dropIfExists('mm_payment_methods');
    }
};
