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
        Schema::table('mm_quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('mm_quotation_items', 'product_id')) {
                // Drop foreign key constraint on mm_products
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'mix_design_id');
            }
        });

        Schema::table('mm_quotation_items', function (Blueprint $table) {
            // Add foreign key constraint on mm_mix_designs
            $table->foreign('mix_design_id')->references('id')->on('mm_mix_designs')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotation_items', function (Blueprint $table) {
            $table->dropForeign(['mix_design_id']);
            $table->renameColumn('mix_design_id', 'product_id');
        });

        Schema::table('mm_quotation_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('mm_products');
        });
    }
};
