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
        if (Schema::hasTable('mm_customer_po_items') && Schema::hasColumn('mm_customer_po_items', 'pump_type') && !Schema::hasColumn('mm_customer_po_items', 'concrete_pump')) {
            Schema::table('mm_customer_po_items', function (Blueprint $table) {
                $table->renameColumn('pump_type', 'concrete_pump');
            });
        }

        if (Schema::hasTable('mm_quotation_items') && Schema::hasColumn('mm_quotation_items', 'pump_type') && !Schema::hasColumn('mm_quotation_items', 'concrete_pump')) {
            Schema::table('mm_quotation_items', function (Blueprint $table) {
                $table->renameColumn('pump_type', 'concrete_pump');
            });
        }

        if (Schema::hasTable('mm_customer_po_item_pump_rates') && Schema::hasColumn('mm_customer_po_item_pump_rates', 'pump_type') && !Schema::hasColumn('mm_customer_po_item_pump_rates', 'concrete_pump')) {
            Schema::table('mm_customer_po_item_pump_rates', function (Blueprint $table) {
                $table->renameColumn('pump_type', 'concrete_pump');
            });
        }

        if (Schema::hasTable('mm_quotation_item_pump_rates') && Schema::hasColumn('mm_quotation_item_pump_rates', 'pump_type') && !Schema::hasColumn('mm_quotation_item_pump_rates', 'concrete_pump')) {
            Schema::table('mm_quotation_item_pump_rates', function (Blueprint $table) {
                $table->renameColumn('pump_type', 'concrete_pump');
            });
        }

        if (Schema::hasTable('mm_pump_rates') && Schema::hasColumn('mm_pump_rates', 'pump_type') && !Schema::hasColumn('mm_pump_rates', 'concrete_pump')) {
            Schema::table('mm_pump_rates', function (Blueprint $table) {
                $table->renameColumn('pump_type', 'concrete_pump');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_customer_po_items') && Schema::hasColumn('mm_customer_po_items', 'concrete_pump') && !Schema::hasColumn('mm_customer_po_items', 'pump_type')) {
            Schema::table('mm_customer_po_items', function (Blueprint $table) {
                $table->renameColumn('concrete_pump', 'pump_type');
            });
        }

        if (Schema::hasTable('mm_quotation_items') && Schema::hasColumn('mm_quotation_items', 'concrete_pump') && !Schema::hasColumn('mm_quotation_items', 'pump_type')) {
            Schema::table('mm_quotation_items', function (Blueprint $table) {
                $table->renameColumn('concrete_pump', 'pump_type');
            });
        }

        if (Schema::hasTable('mm_customer_po_item_pump_rates') && Schema::hasColumn('mm_customer_po_item_pump_rates', 'concrete_pump') && !Schema::hasColumn('mm_customer_po_item_pump_rates', 'pump_type')) {
            Schema::table('mm_customer_po_item_pump_rates', function (Blueprint $table) {
                $table->renameColumn('concrete_pump', 'pump_type');
            });
        }

        if (Schema::hasTable('mm_quotation_item_pump_rates') && Schema::hasColumn('mm_quotation_item_pump_rates', 'concrete_pump') && !Schema::hasColumn('mm_quotation_item_pump_rates', 'pump_type')) {
            Schema::table('mm_quotation_item_pump_rates', function (Blueprint $table) {
                $table->renameColumn('concrete_pump', 'pump_type');
            });
        }

        if (Schema::hasTable('mm_pump_rates') && Schema::hasColumn('mm_pump_rates', 'concrete_pump') && !Schema::hasColumn('mm_pump_rates', 'pump_type')) {
            Schema::table('mm_pump_rates', function (Blueprint $table) {
                $table->renameColumn('concrete_pump', 'pump_type');
            });
        }
    }
};
