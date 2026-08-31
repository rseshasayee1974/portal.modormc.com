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
        Schema::table('mm_personnels', function (Blueprint $table) {
            // Drop global unique index on employee_code if exists
            $table->dropUnique('mm_personnels_employee_code_unique');

            // Virtual column: holds employee_code only when deleted_at IS NULL; otherwise NULL
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            if ($isSqlite) {
                $table->string('active_employee_code')
                    ->virtualAs("CASE WHEN deleted_at IS NULL THEN employee_code ELSE NULL END")
                    ->nullable()
                    ->after('employee_code');
            } else {
                $table->string('active_employee_code')
                    ->virtualAs("CASE WHEN deleted_at IS NULL THEN employee_code ELSE NULL END")
                    ->nullable()
                    ->after('employee_code');
            }

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
