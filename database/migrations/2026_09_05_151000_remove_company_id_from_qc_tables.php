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
        // 1. qc_units
        if (Schema::hasTable('qc_units') && Schema::hasColumn('qc_units', 'company_id')) {
            Schema::table('qc_units', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        // 2. qc_test_types
        if (Schema::hasTable('qc_test_types') && Schema::hasColumn('qc_test_types', 'company_id')) {
            Schema::table('qc_test_types', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        // 3. qc_test_rules
        if (Schema::hasTable('qc_test_rules') && Schema::hasColumn('qc_test_rules', 'company_id')) {
            Schema::table('qc_test_rules', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        // 4. qc_material_tests
        if (Schema::hasTable('qc_material_tests') && Schema::hasColumn('qc_material_tests', 'company_id')) {
            Schema::table('qc_material_tests', function (Blueprint $table) {
                $table->dropUnique('qc_mat_test_unique');
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
                $table->unique(['plant_id', 'material_id', 'test_type_id'], 'qc_mat_test_unique');
            });
        }

        // 5. qc_test_schedules
        if (Schema::hasTable('qc_test_schedules') && Schema::hasColumn('qc_test_schedules', 'company_id')) {
            Schema::table('qc_test_schedules', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        // 6. qc_samples
        if (Schema::hasTable('qc_samples') && Schema::hasColumn('qc_samples', 'company_id')) {
            Schema::table('qc_samples', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        // 7. qc_tests
        if (Schema::hasTable('qc_tests') && Schema::hasColumn('qc_tests', 'company_id')) {
            Schema::table('qc_tests', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('qc_units') && !Schema::hasColumn('qc_units', 'company_id')) {
            Schema::table('qc_units', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('mm_entities')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('qc_test_types') && !Schema::hasColumn('qc_test_types', 'company_id')) {
            Schema::table('qc_test_types', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('mm_entities')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('qc_test_rules') && !Schema::hasColumn('qc_test_rules', 'company_id')) {
            Schema::table('qc_test_rules', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('mm_entities')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('qc_material_tests') && !Schema::hasColumn('qc_material_tests', 'company_id')) {
            Schema::table('qc_material_tests', function (Blueprint $table) {
                $table->dropUnique('qc_mat_test_unique');
                $table->foreignId('company_id')->nullable()->after('id')->constrained('mm_entities')->onDelete('cascade');
                $table->unique(['company_id', 'plant_id', 'material_id', 'test_type_id'], 'qc_mat_test_unique');
            });
        }

        if (Schema::hasTable('qc_test_schedules') && !Schema::hasColumn('qc_test_schedules', 'company_id')) {
            Schema::table('qc_test_schedules', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('mm_entities')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('qc_samples') && !Schema::hasColumn('qc_samples', 'company_id')) {
            Schema::table('qc_samples', function (Blueprint $table) {
                $table->foreignId('company_id')->after('id')->constrained('mm_entities')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('qc_tests') && !Schema::hasColumn('qc_tests', 'company_id')) {
            Schema::table('qc_tests', function (Blueprint $table) {
                $table->foreignId('company_id')->after('id')->constrained('mm_entities')->onDelete('cascade');
            });
        }
    }
};
