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
        Schema::table('mm_concrete_quality_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'account_name')) {
                $table->string('account_name')->nullable()->after('tested_by');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'patron_id')) {
                $table->foreignId('patron_id')->nullable()->after('account_name')->constrained('mm_patrons')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('patron_id')->constrained('mm_invoices')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'invoice_no')) {
                $table->string('invoice_no')->nullable()->after('invoice_id');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'grade')) {
                $table->string('grade')->nullable()->after('invoice_no');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'concrete_date')) {
                $table->date('concrete_date')->nullable()->after('grade');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'age_of_test_days')) {
                $table->integer('age_of_test_days')->default(7)->after('concrete_date');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'date_of_testing')) {
                $table->date('date_of_testing')->nullable()->after('age_of_test_days');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'project')) {
                $table->string('project')->nullable()->after('date_of_testing');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'billing_address')) {
                $table->text('billing_address')->nullable()->after('project');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('billing_address');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'description')) {
                $table->text('description')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'test_number')) {
                $table->string('test_number')->nullable()->after('description');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'dimension_length')) {
                $table->decimal('dimension_length', 6, 2)->default(15)->after('test_number');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'dimension_width')) {
                $table->decimal('dimension_width', 6, 2)->default(15)->after('dimension_length');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'dimension_height')) {
                $table->decimal('dimension_height', 6, 2)->default(15)->after('dimension_width');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'fresh_unit_weight')) {
                $table->decimal('fresh_unit_weight', 8, 2)->nullable()->after('dimension_height');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'lab_technician')) {
                $table->string('lab_technician')->nullable()->after('fresh_unit_weight');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'field_technician')) {
                $table->string('field_technician')->nullable()->after('lab_technician');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'ident_mark')) {
                $table->string('ident_mark')->nullable()->after('field_technician');
            }
            if (!Schema::hasColumn('mm_concrete_quality_tests', 'avg_compressive_strength')) {
                $table->decimal('avg_compressive_strength', 8, 2)->nullable()->after('ident_mark');
            }
        });

        if (!Schema::hasTable('mm_concrete_quality_test_specimens')) {
            Schema::create('mm_concrete_quality_test_specimens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('concrete_quality_test_id');
                $table->foreign('concrete_quality_test_id', 'fk_qc_specimens_test_id')
                      ->references('id')
                      ->on('mm_concrete_quality_tests')
                      ->cascadeOnDelete();
                $table->string('ident_mark')->nullable();
                $table->decimal('weight_kg', 8, 2)->nullable();
                $table->decimal('load_kn', 8, 2)->nullable();
                $table->decimal('compressive_strength', 8, 2)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_concrete_quality_test_specimens');

        Schema::table('mm_concrete_quality_tests', function (Blueprint $table) {
            $columns = [
                'account_name', 'patron_id', 'invoice_id', 'invoice_no',
                'grade', 'concrete_date', 'age_of_test_days', 'date_of_testing',
                'project', 'billing_address', 'shipping_address', 'description',
                'test_number', 'dimension_length', 'dimension_width', 'dimension_height',
                'fresh_unit_weight', 'lab_technician', 'field_technician', 'ident_mark',
                'avg_compressive_strength'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('mm_concrete_quality_tests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
