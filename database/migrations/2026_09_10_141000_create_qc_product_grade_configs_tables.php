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
        if (!Schema::hasTable('mm_qc_product_grade_configs')) {
            Schema::create('mm_qc_product_grade_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
                $table->foreignId('material_id')->constrained('mm_products')->onDelete('cascade');
                $table->foreignId('concrete_grade_id')->nullable()->constrained('mm_concrete_grades')->onDelete('set null');
                $table->foreignId('test_type_id')->constrained('mm_qc_test_types')->onDelete('cascade');
                $table->foreignId('standard_id')->nullable()->constrained('mm_qc_standards')->onDelete('set null');
                $table->string('specimen_shape', 50)->default('Cube');
                $table->string('specimen_dimensions', 100)->default('150 MM X 150 MM X 150 MM');
                $table->integer('specimens_per_set')->default(3);
                $table->integer('total_sets')->default(3);
                $table->json('formula_bindings')->nullable();
                $table->integer('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->auditColumns();

                $table->index(['material_id', 'concrete_grade_id', 'test_type_id', 'is_active'], 'qc_pg_config_mat_grade_idx');
            });
        }

        if (!Schema::hasTable('mm_qc_config_age_milestones')) {
            Schema::create('mm_qc_config_age_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('config_id')->constrained('mm_qc_product_grade_configs')->onDelete('cascade');
                $table->integer('set_number')->default(1);
                $table->integer('age_days');
                $table->string('age_label', 50);
                $table->decimal('target_percentage', 6, 2)->nullable();
                $table->decimal('target_value', 12, 4)->nullable();
                $table->decimal('min_value', 12, 4)->nullable();
                $table->decimal('max_value', 12, 4)->nullable();
                $table->string('rule_type', 50)->default('GREATER_THAN_OR_EQUAL');
                $table->decimal('tolerance', 12, 4)->nullable();
                $table->integer('display_order')->default(0);
                $table->auditColumns();

                $table->index(['config_id', 'age_days'], 'qc_milestone_config_age_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_qc_config_age_milestones');
        Schema::dropIfExists('mm_qc_product_grade_configs');
    }
};
