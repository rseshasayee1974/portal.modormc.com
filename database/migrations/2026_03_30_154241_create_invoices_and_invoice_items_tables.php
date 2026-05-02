<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Covers: invoices, invoice_items, and order_taxes (polymorphic tax split-up)
     */
    public function up(): void
    {
        Schema::create('mm_invoices', function (Blueprint $table) {
            $table->id();

            // Context
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('vendor_id');    // seller (patron)
            $table->unsignedBigInteger('customer_id');  // buyer (patron)

            // GST / Compliance
            $table->string('supplier_gstin', 15);
            $table->string('customer_gstin', 15)->nullable();
            $table->string('place_of_supply', 2);       // state code e.g. "33" for TN

            // Invoice reference
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Tax configuration: references mm_taxes
            $table->unsignedBigInteger('tax_id')->nullable();

            // Totals (all computed and cached on model)
            $table->decimal('subtotal', 17, 2)->default(0);
            $table->decimal('discount_total', 17, 2)->default(0);
            $table->decimal('tax_amount', 17, 2)->default(0);
            $table->decimal('adjustment', 17, 2)->default(0);
            $table->decimal('total_amount', 17, 2)->default(0);
            $table->decimal('round_off', 10, 2)->default(0);

            // Lifecycle
            $table->string('status', 100)->default('draft'); // draft|approved|paid|cancelled
            $table->tinyInteger('is_active')->default(1);

            // Auditing
            $table->auditColumns();

            $table->foreign('plant_id')->references('id')->on('mm_plants');
            $table->foreign('vendor_id')->references('id')->on('mm_patrons');
            $table->foreign('customer_id')->references('id')->on('mm_patrons');
            $table->foreign('tax_id')->references('id')->on('mm_taxes')->nullOnDelete();
        });

        Schema::create('mm_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');

            // Item Breakdown
            $table->string('item_name')->nullable();
            $table->string('hsn_code', 10)->nullable();             // mandatory for GST
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('price_unit', 15, 2)->default(0);

            // Discount
            $table->string('discount_type', 20)->default('%'); // 'percent' | 'fixed'
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('discount_amount', 17, 2)->default(0);

            // Tax-applied sub-totals
            $table->decimal('subtotal', 17, 2)->default(0);      // after discount, before tax
            $table->decimal('line_tax_amount', 15, 2)->default(0); // total tax for this line
            $table->decimal('line_total', 17, 2)->default(0);     // final line total

            $table->auditColumns();

            $table->foreign('invoice_id')->references('id')->on('mm_invoices')->cascadeOnDelete();
        });

        // Order Taxes: polymorphic tax split (CGST/SGST/IGST) per line item or per invoice
        Schema::create('mm_order_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();

            // Polymorphic — ties a tax split to Invoice or InvoiceItem
            $table->string('order_type')->nullable();    // e.g. 'App\Models\Invoice' or 'App\Models\InvoiceItem'
            $table->unsignedBigInteger('order_id');      // morphable FK

            $table->unsignedBigInteger('order_items_id')->nullable(); // optional item-level ref
            $table->unsignedBigInteger('account_id')->nullable();     // GL account
            $table->foreignId('entity_id')->nullable()->constrained('mm_entities')->nullOnDelete();

            // Tax component meta
            $table->string('name')->nullable();          // e.g. "CGST 9%", "IGST 18%"
            $table->decimal('rate', 8, 4)->default(0);  // tax rate %
            $table->decimal('amount', 17, 2)->default(0); // computed tax amount

            $table->tinyInteger('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_order_taxes');
        Schema::dropIfExists('mm_invoice_items');
        Schema::dropIfExists('mm_invoices');
    }
};
