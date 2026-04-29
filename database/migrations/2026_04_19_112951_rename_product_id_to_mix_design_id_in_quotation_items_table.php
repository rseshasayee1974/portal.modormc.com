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
                $table->renameColumn('product_id', 'mix_design_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotation_items', function (Blueprint $table) {
            $table->renameColumn('mix_design_id', 'product_id');
        });
    }
};
