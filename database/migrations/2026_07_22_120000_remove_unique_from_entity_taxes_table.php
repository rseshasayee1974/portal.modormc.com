<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = Schema::getIndexes('mm_entity_taxes');
        $hasIndex = collect($indexes)->contains(function ($index) {
            return $index['name'] === 'mm_entity_taxes_entity_id_tax_type_unique';
        });

        if ($hasIndex) {
            Schema::table('mm_entity_taxes', function (Blueprint $table) {
                $table->dropUnique('mm_entity_taxes_entity_id_tax_type_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = Schema::getIndexes('mm_entity_taxes');
        $hasIndex = collect($indexes)->contains(function ($index) {
            return $index['name'] === 'mm_entity_taxes_entity_id_tax_type_unique';
        });

        if (!$hasIndex) {
            Schema::table('mm_entity_taxes', function (Blueprint $table) {
                $table->unique(['entity_id', 'tax_type']);
            });
        }
    }
};
