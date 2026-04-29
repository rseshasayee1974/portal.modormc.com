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
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->unique(['plant_id', 'legal_name'], 'mm_patrons_plant_legal_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->dropUnique('mm_patrons_entity_legal_name_unique');
        });
    }
};
