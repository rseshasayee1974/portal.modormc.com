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
        Schema::table('mm_invoices', function (Blueprint $table) {
            // Index for date filtering and type optimization
            $table->index(['plant_id', 'invoice_type', 'invoice_date'], 'idx_invoices_plant_type_date');
            $table->index('partner_id', 'idx_invoices_partner_id');
            $table->index('invoice_date', 'idx_invoices_date');
        });

        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            // Index for purchase orders filtering
            $table->index(['plant_id', 'date_order'], 'idx_po_plant_date');
            $table->index('vendor_id', 'idx_po_vendor_id');
        });

        Schema::table('mm_order_taxes', function (Blueprint $table) {
            // Index for tax split mapping
            $table->index(['order_items_id', 'name'], 'idx_order_taxes_item_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_plant_type_date');
            $table->dropIndex('idx_invoices_partner_id');
            $table->dropIndex('idx_invoices_date');
        });

        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_plant_date');
            $table->dropIndex('idx_po_vendor_id');
        });

        Schema::table('mm_order_taxes', function (Blueprint $table) {
            $table->dropIndex('idx_order_taxes_item_name');
        });
    }
};
