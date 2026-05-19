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
        Schema::table('mm_state_codes', function (Blueprint $table) {
            // Drop unique index to allow multiple zipcodes/areas under the same state_code
            $table->dropUnique(['country_id', 'state_code']);
            
            $table->string('zipcode', 20)->nullable()->index();
            $table->string('area', 150)->nullable();
            $table->string('district', 150)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_state_codes', function (Blueprint $table) {
            $table->dropColumn(['zipcode', 'area', 'district']);
            
            $table->unique(['country_id', 'state_code']);
        });
    }
};
