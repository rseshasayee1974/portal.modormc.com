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
        // Drop existing tables if present to ensure clean schema build
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('qc_test_results');
        Schema::dropIfExists('qc_test_measurements');
        Schema::dropIfExists('qc_tests');
        Schema::dropIfExists('qc_samples');
        Schema::dropIfExists('qc_test_schedules');
        Schema::dropIfExists('qc_material_tests');
        Schema::dropIfExists('qc_test_rules');
        Schema::dropIfExists('qc_test_parameters');
        Schema::dropIfExists('qc_test_types');
        Schema::enableForeignKeyConstraints();

        // 1. QC Test Types Configuration
        Schema::create('qc_test_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->enum('category', ['Aggregate', 'Cement', 'Concrete', 'Admixture', 'Water', 'General'])->default('Aggregate');
            $table->string('material_type', 50)->nullable();
            $table->string('standard_reference', 150)->nullable();
            $table->enum('calculation_type', ['formula', 'manual', 'custom_class'])->default('formula');
            $table->string('custom_calculator_class', 255)->nullable();
            $table->string('layout_type', 50)->default('SINGLE_TRIAL');
            $table->json('grid_config')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->index(['plant_id', 'is_active']);
            $table->index(['code', 'category']);
        });

        // 2. QC Test Parameters
        Schema::create('qc_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_type_id')->constrained('qc_test_types')->onDelete('cascade');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->enum('data_type', ['decimal', 'integer', 'text', 'boolean', 'date', 'select'])->default('decimal');
            $table->string('unit', 30)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_calculated')->default(false);
            $table->string('formula', 255)->nullable();
            $table->json('options')->nullable();
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->index(['test_type_id', 'display_order']);
        });

        // 3. QC Acceptance Rules
        Schema::create('qc_test_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
            $table->foreignId('test_type_id')->constrained('qc_test_types')->onDelete('cascade');
            $table->foreignId('parameter_id')->nullable()->constrained('qc_test_parameters')->onDelete('cascade');
            $table->foreignId('material_id')->nullable()->constrained('mm_products')->onDelete('cascade');
            $table->enum('rule_type', [
                'RANGE',
                'GREATER_THAN',
                'GREATER_THAN_OR_EQUAL',
                'LESS_THAN',
                'LESS_THAN_OR_EQUAL',
                'EQUAL',
                'TARGET_TOLERANCE'
            ])->default('RANGE');
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->decimal('target_value', 12, 4)->nullable();
            $table->decimal('tolerance', 12, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('standard_reference', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->index(['test_type_id', 'material_id', 'is_active']);
        });

        // 4. Material-Test Mapping
        Schema::create('qc_material_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('mm_products')->onDelete('cascade');
            $table->foreignId('test_type_id')->constrained('qc_test_types')->onDelete('cascade');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->unique(['plant_id', 'material_id', 'test_type_id'], 'qc_mat_test_unique');
        });

        // 5. QC Test Schedules / Frequencies
        Schema::create('qc_test_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('mm_products')->onDelete('cascade');
            $table->foreignId('test_type_id')->constrained('qc_test_types')->onDelete('cascade');
            $table->enum('frequency_type', [
                'PER_BATCH',
                'PER_LOT',
                'PER_DELIVERY',
                'PER_SHIFT',
                'HOURLY',
                'EVERY_N_HOURS',
                'DAILY',
                'WEEKLY',
                'MONTHLY',
                'MANUAL'
            ])->default('DAILY');
            $table->integer('frequency_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->index(['material_id', 'test_type_id', 'is_active']);
        });

        // 6. QC Samples
        Schema::create('qc_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->string('sample_no', 50);
            $table->dateTime('sample_date');
            $table->foreignId('material_id')->constrained('mm_products')->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('mm_patrons')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->onDelete('set null');
            $table->foreignId('inward_id')->nullable()->constrained('mm_purchase_order_inwards')->onDelete('set null');
            $table->foreignId('batch_id')->nullable()->constrained('mm_batches')->onDelete('set null');
            $table->foreignId('dispatch_id')->nullable()->constrained('mm_dispatches')->onDelete('set null');
            $table->string('source_location', 150)->nullable();
            $table->string('sample_quantity', 50)->nullable();
            $table->foreignId('sampled_by')->constrained('mm_users')->onDelete('cascade');
            $table->enum('status', ['draft', 'pending_test', 'testing', 'completed', 'rejected'])->default('pending_test');
            $table->text('remarks')->nullable();
            $table->auditColumns();

            $table->index(['plant_id', 'material_id', 'status']);
            $table->index('sample_no');
        });

        // 7. QC Tests
        Schema::create('qc_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->foreignId('sample_id')->constrained('qc_samples')->onDelete('cascade');
            $table->foreignId('test_type_id')->constrained('qc_test_types')->onDelete('cascade');
            $table->string('test_no', 50);
            $table->dateTime('test_date');
            $table->foreignId('tested_by')->constrained('mm_users')->onDelete('cascade');
            $table->enum('overall_status', ['pending', 'pass', 'fail', 'retest', 'hold'])->default('pending');
            $table->dateTime('evaluated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('mm_users')->onDelete('set null');
            $table->dateTime('reviewed_at')->nullable();
            $table->enum('approval_status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->text('retest_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->auditColumns();

            $table->index(['plant_id', 'overall_status']);
            $table->index('test_no');
        });

        // 8. QC Test Measurements
        Schema::create('qc_test_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_test_id')->constrained('qc_tests')->onDelete('cascade');
            $table->foreignId('parameter_id')->constrained('qc_test_parameters')->onDelete('cascade');
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_numeric', 12, 4)->nullable();
            $table->boolean('is_calculated')->default(false);
            $table->integer('row_index')->default(0);
            $table->timestamps();

            $table->index(['qc_test_id', 'parameter_id']);
        });

        // 9. QC Test Results (Calculated final outputs and pass/fail evaluation)
        Schema::create('qc_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_test_id')->constrained('qc_tests')->onDelete('cascade');
            $table->foreignId('parameter_id')->constrained('qc_test_parameters')->onDelete('cascade');
            $table->decimal('final_value', 12, 4)->nullable();
            $table->string('final_text', 255)->nullable();
            $table->enum('status', ['PASS', 'FAIL', 'NONE'])->default('NONE');
            $table->json('criteria_snapshot')->nullable();
            $table->timestamps();

            $table->index(['qc_test_id', 'parameter_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_test_results');
        Schema::dropIfExists('qc_test_measurements');
        Schema::dropIfExists('qc_tests');
        Schema::dropIfExists('qc_samples');
        Schema::dropIfExists('qc_test_schedules');
        Schema::dropIfExists('qc_material_tests');
        Schema::dropIfExists('qc_test_rules');
        Schema::dropIfExists('qc_test_parameters');
        Schema::dropIfExists('qc_test_types');
    }
};
