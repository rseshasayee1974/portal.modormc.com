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
        $testTypesTable = Schema::hasTable('mm_qc_test_types') ? 'mm_qc_test_types' : (Schema::hasTable('qc_test_types') ? 'qc_test_types' : null);
        if ($testTypesTable) {
            Schema::table($testTypesTable, function (Blueprint $table) use ($testTypesTable) {
                if (!Schema::hasColumn($testTypesTable, 'specimen_count')) {
                    $table->integer('specimen_count')->default(0)->after('layout_type');
                }
                if (!Schema::hasColumn($testTypesTable, 'specimen_shape')) {
                    $table->string('specimen_shape', 50)->nullable()->after('specimen_count');
                }
                if (!Schema::hasColumn($testTypesTable, 'specimen_dimensions')) {
                    $table->string('specimen_dimensions', 100)->nullable()->after('specimen_shape');
                }
            });
        }

        $parametersTable = Schema::hasTable('mm_qc_test_parameters') ? 'mm_qc_test_parameters' : (Schema::hasTable('qc_test_parameters') ? 'qc_test_parameters' : null);
        if ($parametersTable) {
            Schema::table($parametersTable, function (Blueprint $table) use ($parametersTable) {
                if (!Schema::hasColumn($parametersTable, 'scope')) {
                    $table->string('scope', 30)->default('test')->after('unit'); // test, specimen, summary
                }
                if (!Schema::hasColumn($parametersTable, 'calculation_scope')) {
                    $table->string('calculation_scope', 30)->nullable()->after('formula'); // row, column_avg, column_sum, formula
                }
                if (!Schema::hasColumn($parametersTable, 'formula_expression')) {
                    $table->text('formula_expression')->nullable()->after('calculation_scope');
                }
                if (!Schema::hasColumn($parametersTable, 'is_summary')) {
                    $table->boolean('is_summary')->default(false)->after('is_calculated');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $testTypesTable = Schema::hasTable('mm_qc_test_types') ? 'mm_qc_test_types' : (Schema::hasTable('qc_test_types') ? 'qc_test_types' : null);
        if ($testTypesTable) {
            Schema::table($testTypesTable, function (Blueprint $table) use ($testTypesTable) {
                $cols = ['specimen_count', 'specimen_shape', 'specimen_dimensions'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn($testTypesTable, $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        $parametersTable = Schema::hasTable('mm_qc_test_parameters') ? 'mm_qc_test_parameters' : (Schema::hasTable('qc_test_parameters') ? 'qc_test_parameters' : null);
        if ($parametersTable) {
            Schema::table($parametersTable, function (Blueprint $table) use ($parametersTable) {
                $cols = ['scope', 'calculation_scope', 'formula_expression', 'is_summary'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn($parametersTable, $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
