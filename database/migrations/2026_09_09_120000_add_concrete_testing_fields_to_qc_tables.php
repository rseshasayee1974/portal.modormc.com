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
        if (Schema::hasTable('mm_qc_samples')) {
            Schema::table('mm_qc_samples', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_qc_samples', 'concrete_grade_id')) {
                    $table->unsignedBigInteger('concrete_grade_id')->nullable()->after('material_id');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'slump_mm')) {
                    $table->decimal('slump_mm', 6, 2)->nullable()->after('source_location');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'concrete_temp_c')) {
                    $table->decimal('concrete_temp_c', 5, 2)->nullable()->after('slump_mm');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'ambient_temp_c')) {
                    $table->decimal('ambient_temp_c', 5, 2)->nullable()->after('concrete_temp_c');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'specimen_size')) {
                    $table->string('specimen_size', 50)->default('150x150x150 mm')->after('ambient_temp_c');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'specimen_count')) {
                    $table->integer('specimen_count')->default(6)->after('specimen_size');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'curing_tank_id')) {
                    $table->string('curing_tank_id', 100)->nullable()->after('specimen_count');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'truck_no')) {
                    $table->string('truck_no', 100)->nullable()->after('dispatch_id');
                }
                if (!Schema::hasColumn('mm_qc_samples', 'site_name')) {
                    $table->string('site_name', 150)->nullable()->after('truck_no');
                }
            });
        }

        if (Schema::hasTable('mm_qc_tests')) {
            Schema::table('mm_qc_tests', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_qc_tests', 'scheduled_date')) {
                    $table->date('scheduled_date')->nullable()->after('test_no');
                }
                if (!Schema::hasColumn('mm_qc_tests', 'age_days')) {
                    $table->integer('age_days')->nullable()->after('scheduled_date');
                }
                if (!Schema::hasColumn('mm_qc_tests', 'target_strength')) {
                    $table->decimal('target_strength', 8, 2)->nullable()->after('age_days');
                }
                if (!Schema::hasColumn('mm_qc_tests', 'min_strength')) {
                    $table->decimal('min_strength', 8, 2)->nullable()->after('target_strength');
                }
                if (!Schema::hasColumn('mm_qc_tests', 'unit')) {
                    $table->string('unit', 30)->default('MPa')->after('min_strength');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_qc_samples')) {
            Schema::table('mm_qc_samples', function (Blueprint $table) {
                $cols = ['concrete_grade_id', 'slump_mm', 'concrete_temp_c', 'ambient_temp_c', 'specimen_size', 'specimen_count', 'curing_tank_id', 'truck_no', 'site_name'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('mm_qc_samples', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('mm_qc_tests')) {
            Schema::table('mm_qc_tests', function (Blueprint $table) {
                $cols = ['scheduled_date', 'age_days', 'target_strength', 'min_strength', 'unit'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('mm_qc_tests', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};