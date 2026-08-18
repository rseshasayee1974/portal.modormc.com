<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the mm_item_pump_rates table with explicit foreign key columns
     * to refer to quotation/customer_po and their corresponding line items.
     */
    public function up(): void
    {
        Schema::create('mm_quotation_item_pump_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->nullable()->constrained('mm_quotations')->nullOnDelete();
            $table->foreignId('quotation_item_id')->constrained('mm_quotation_items')->cascadeOnDelete();
            $table->string('concrete_pump', 100);
            $table->decimal('pump_rate', 10, 2)->default(0);
            $table->auditColumns();
            $table->unique(['quotation_item_id', 'concrete_pump'], 'uq_qi_pump_rates');
        });

        Schema::create('mm_customer_po_item_pump_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_po_id')->nullable()->constrained('mm_customer_pos')->nullOnDelete();
            $table->foreignId('customer_po_item_id')->constrained('mm_customer_po_items')->cascadeOnDelete();
            $table->string('concrete_pump', 100);
            $table->decimal('pump_rate', 10, 2)->default(0);
            $table->auditColumns();
            $table->unique(['customer_po_item_id', 'concrete_pump'], 'uq_cpo_pump_rates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_quotation_item_pump_rates');
        Schema::dropIfExists('mm_customer_po_item_pump_rates');
    }
};
