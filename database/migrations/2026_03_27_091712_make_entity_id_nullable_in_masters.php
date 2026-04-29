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
        $tables = [
            'mm_patrons', 
            'mm_machines', 
            'mm_taxes', 
            'mm_products', 
            'mm_product_categories',
            'purchase_order_items',
            'contacts',
            'addresses',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $tableLayout) use ($table) {
                if (Schema::hasColumn($table, 'entity_id')) {
                    $tableLayout->unsignedBigInteger('entity_id')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'mm_patrons', 
            'mm_machines', 
            'mm_taxes', 
            'mm_products', 
            'mm_product_categories',
            'purchase_order_items',
            'contacts',
            'addresses',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $tableLayout) use ($table) {
                if (Schema::hasColumn($table, 'entity_id')) {
                    $tableLayout->unsignedBigInteger('entity_id')->nullable(false)->change();
                }
            });
        }
    }
};
