<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop existing global unique index on employee_code if exists
        try {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->dropUnique('mm_personnels_employee_code_unique');
            });
        } catch (\Throwable $e) {
            // Index already dropped or doesn't exist
        }

        // 2. Drop active_employee_code column if partially created from a failed run
        if (Schema::hasColumn('mm_personnels', 'active_employee_code')) {
            Schema::table('mm_personnels', function (Blueprint $table) {
                $table->dropColumn('active_employee_code');
            });
        }

        // 3. Stored generated column: supports unique index in MyISAM, InnoDB, and SQLite
        Schema::table('mm_personnels', function (Blueprint $table) {
            $table->string('active_employee_code')
                ->storedAs("CASE WHEN deleted_at IS NULL THEN employee_code ELSE NULL END")
                ->nullable()
                ->after('employee_code');

            // Unique index per plant only among active records
            $table->unique(['plant_id', 'active_employee_code'], 'mm_personnels_plant_active_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_personnels', function (Blueprint $table) {
            $table->dropUnique('mm_personnels_plant_active_code_unique');
            $table->dropColumn('active_employee_code');
            $table->unique('employee_code', 'mm_personnels_employee_code_unique');
        });
    }
};

