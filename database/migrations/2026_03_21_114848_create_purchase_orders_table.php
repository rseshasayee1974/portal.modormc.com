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
        Schema::create('mm_purchase_orders', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('entity_id')->constrained('mm_entities')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('mm_patrons')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('mm_machines')->nullOnDelete();

            $table->string('po_number', 50);
            $table->string('ref_no', 100)->nullable();
            $table->string('bill_number', 150)->nullable();
            $table->date('billed_date')->nullable();

            $table->date('date_order')->nullable();
            $table->date('date_approve')->nullable();
            $table->date('date_planned')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('due_date')->nullable();

            $table->string('partner_reference', 100)->nullable();

            $table->string('state')->default('draft'); // draft, to_approve, approved, purchase, done, cancel
            
            // Statuses for specific workflows
            $table->tinyInteger('approve_status')->default(0);
            $table->tinyInteger('invoice_status')->default(0);
            $table->tinyInteger('receipt_status')->default(0);
            $table->tinyInteger('journal_status')->default(0);
            $table->tinyInteger('closed_status')->default(0);

            // Financials
            $table->foreignId('currency_id')->nullable()->constrained('mm_currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 17, 6)->default(1.0);
            
            $table->decimal('amount_untaxed', 17, 2)->default(0);
            $table->decimal('amount_tax', 17, 2)->default(0);
            $table->decimal('amount_total', 17, 2)->default(0);
            $table->decimal('discount_amount', 17, 2)->default(0);
            $table->decimal('shipping_charges', 17, 2)->default(0);
            $table->decimal('adjustment', 17, 2)->default(0);
            $table->decimal('rounding_value', 17, 2)->default(0);

            // Taxes
            $table->foreignId('common_tax_id')->nullable()->constrained('mm_taxes');
            $table->foreignId('shipping_tax_id')->nullable()->constrained('mm_taxes');

            $table->string('origin', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();

            // Audit
            $table->auditColumns();

            $table->index('vendor_id');
            $table->index('vehicle_id');
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_purchase_orders');
    }
};
