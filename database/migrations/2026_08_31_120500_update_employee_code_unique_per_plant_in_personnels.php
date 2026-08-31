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
        Schema::table('mm_personnels', function (Blueprint $table) {
            // Drop global unique index on employee_code if exists
            $table->dropUnique('mm_personnels_employee_code_unique');
            
            // Add composite unique index per plant
            $table->unique(['plant_id', 'employee_code'], 'mm_personnels_plant_employee_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_personnels', function (Blueprint $table) {
            $table->dropUnique('mm_personnels_plant_employee_code_unique');
            $table->unique('employee_code', 'mm_personnels_employee_code_unique');
        });
    }
};
