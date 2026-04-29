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
            'mm_sites',
            'mm_personnels',
            'mm_products',
            'mm_product_categories',
            'mm_product_units',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'entity_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('entity_id')->after('id')->nullable();
                    // Foreign key for consistency
                    $table->foreign('entity_id')->references('id')->on('mm_entities')->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'mm_patrons',
            'mm_sites',
            'mm_personnels',
            'mm_products',
            'mm_product_categories',
            'mm_product_units',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'entity_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['entity_id']);
                    $table->dropColumn('entity_id');
                });
            }
        }
    }
};
