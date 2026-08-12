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
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_customer_pos', 'customer_po_reference')) {
                $table->string('customer_po_reference', 150)->nullable()->after('reference')
                      ->comment("Customer's own PO / order reference number");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->dropColumn('customer_po_reference');
        });
    }
};
