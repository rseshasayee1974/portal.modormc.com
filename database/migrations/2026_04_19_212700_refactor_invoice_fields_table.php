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
        // Drop foreign keys if exists
        try {
            Schema::table('mm_invoices', function (Blueprint $table) {
                $table->dropForeign(['vendor_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('mm_invoices', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
            });
        } catch (\Exception $e) {}

        // Drop columns safely
        Schema::table('mm_invoices', function (Blueprint $table) {
            $colsToDrop = array_filter(['vendor_id', 'customer_id', 'supplier_gstin', 'customer_gstin', 'place_of_supply'], function($col) {
                return Schema::hasColumn('mm_invoices', $col);
            });
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // Add new columns
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id')->nullable()->after('plant_id');
            $table->string('invoice_type')->nullable()->after('partner_id'); // e.g. 'sales', 'purchase'
            $table->string('invoice_label')->nullable()->after('invoice_type');
            $table->unsignedBigInteger('ref_id')->nullable()->after('invoice_label');
            $table->string('ref_title')->nullable()->after('ref_id');
            
            if (!Schema::hasColumn('mm_invoices', 'prefix')) {
                $table->string('prefix')->nullable()->after('invoice_number');
            }

            // Foreign key
            $table->foreign('partner_id')->references('id')->on('mm_patrons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropColumn(['partner_id', 'invoice_type', 'invoice_label', 'ref_id', 'ref_title', 'prefix']);

            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('supplier_gstin', 15)->nullable();
            $table->string('customer_gstin', 15)->nullable();
            $table->string('place_of_supply', 2)->nullable();

            $table->foreign('vendor_id')->references('id')->on('mm_patrons');
            $table->foreign('customer_id')->references('id')->on('mm_patrons');
        });
    }
};
