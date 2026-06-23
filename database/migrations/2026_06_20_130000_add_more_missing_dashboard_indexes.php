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
        Schema::table('mm_work_orders', function (Blueprint $table) {
            $table->index(['plant_id', 'status', 'created_at'], 'idx_work_orders_plant_status_created');
            $table->index(['plant_id', 'customer_id', 'status'], 'idx_work_orders_plant_customer_status');
        });

        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->index(['plant_id', 'vendor_id', 'date_order'], 'idx_po_plant_vendor_date');
        });

        Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
            $table->index(['plant_id', 'partner_id', 'created_at'], 'idx_journal_lines_plant_partner_created');
        });

        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->index(['plant_id', 'invoice_type', 'partner_id', 'invoice_date'], 'idx_invoices_plant_type_partner_date');
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->index(['plant_id', 'customer_id', 'dispatch_time'], 'idx_dispatches_plant_customer_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_work_orders', function (Blueprint $table) {
            $table->dropIndex('idx_work_orders_plant_status_created');
            $table->dropIndex('idx_work_orders_plant_customer_status');
        });

        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_plant_vendor_date');
        });

        Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex('idx_journal_lines_plant_partner_created');
        });

        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_plant_type_partner_date');
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->dropIndex('idx_dispatches_plant_customer_time');
        });
    }
};
