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
        if (Schema::hasTable('mm_qc_samples') && Schema::hasColumn('mm_qc_samples', 'material_id')) {
            try {
                Schema::table('mm_qc_samples', function (Blueprint $table) {
                    $table->unsignedBigInteger('material_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                DB::statement('ALTER TABLE `mm_qc_samples` MODIFY `material_id` BIGINT UNSIGNED NULL;');
            }
        }

        if (Schema::hasTable('mm_qc_samples') && !Schema::hasColumn('mm_qc_samples', 'tested_by')) {
            Schema::table('mm_qc_samples', function (Blueprint $table) {
                $table->unsignedBigInteger('tested_by')->nullable()->after('sampled_by');
            });
        }

        if (Schema::hasTable('mm_qc_tests') && Schema::hasColumn('mm_qc_tests', 'test_date')) {
            try {
                Schema::table('mm_qc_tests', function (Blueprint $table) {
                    $table->dateTime('test_date')->nullable()->change();
                });
            } catch (\Throwable $e) {
                DB::statement('ALTER TABLE `mm_qc_tests` MODIFY `test_date` DATETIME NULL;');
            }
        }

        if (Schema::hasTable('mm_qc_tests') && Schema::hasColumn('mm_qc_tests', 'tested_by')) {
            try {
                Schema::table('mm_qc_tests', function (Blueprint $table) {
                    $table->dropForeign(['tested_by']);
                });
            } catch (\Throwable $e) {}
            try {
                Schema::table('mm_qc_tests', function (Blueprint $table) {
                    $table->unsignedBigInteger('tested_by')->nullable()->change();
                });
            } catch (\Throwable $e) {
                try { DB::statement('ALTER TABLE `mm_qc_tests` MODIFY `tested_by` BIGINT UNSIGNED NULL;'); } catch (\Throwable $ex) {}
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_qc_samples') && Schema::hasColumn('mm_qc_samples', 'material_id')) {
            try {
                Schema::table('mm_qc_samples', function (Blueprint $table) {
                    $table->unsignedBigInteger('material_id')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                DB::statement('ALTER TABLE `mm_qc_samples` MODIFY `material_id` BIGINT UNSIGNED NOT NULL;');
            }
        }

        if (Schema::hasTable('mm_qc_tests') && Schema::hasColumn('mm_qc_tests', 'test_date')) {
            try {
                Schema::table('mm_qc_tests', function (Blueprint $table) {
                    $table->dateTime('test_date')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                DB::statement('ALTER TABLE `mm_qc_tests` MODIFY `test_date` DATETIME NOT NULL;');
            }
        }
    }
};
