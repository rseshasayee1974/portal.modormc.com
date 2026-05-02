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
        Schema::table('mm_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('mix_design_id')->nullable()->after('invoice_id');
            $table->unsignedBigInteger('uom_id')->nullable()->after('mix_design_id');

            $table->foreign('mix_design_id')->references('id')->on('mm_mix_designs')->nullOnDelete();
            $table->foreign('uom_id')->references('id')->on('mm_product_units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['mix_design_id']);
            $table->dropForeign(['uom_id']);
            $table->dropColumn(['mix_design_id', 'uom_id']);
        });
    }
};
