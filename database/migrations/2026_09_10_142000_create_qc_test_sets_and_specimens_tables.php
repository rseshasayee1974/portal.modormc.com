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
        if (!Schema::hasTable('mm_qc_test_sets')) {
            Schema::create('mm_qc_test_sets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qc_test_id')->constrained('mm_qc_tests')->onDelete('cascade');
                $table->integer('set_number')->default(1);
                $table->foreignId('age_milestone_id')->nullable()->constrained('mm_qc_config_age_milestones')->onDelete('set null');
                $table->integer('age_days')->nullable();
                $table->string('age_label', 50)->nullable();
                $table->dateTime('casting_date')->nullable();
                $table->string('place_of_casting', 150)->nullable();
                $table->date('scheduled_testing_date')->nullable();
                $table->dateTime('testing_date')->nullable();
                $table->decimal('target_strength', 12, 4)->nullable();
                $table->decimal('min_strength', 12, 4)->nullable();
                $table->decimal('average_strength', 12, 4)->nullable();
                $table->string('status', 30)->default('pending'); // pending, pass, fail, incomplete, error
                $table->string('error_reason', 255)->nullable();
                $table->string('client_sign_name', 150)->nullable();
                $table->dateTime('client_signed_at')->nullable();
                $table->string('qc_sign_name', 150)->nullable();
                $table->dateTime('qc_signed_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['qc_test_id', 'set_number']);
            });
        }

        if (!Schema::hasTable('mm_qc_test_specimens')) {
            Schema::create('mm_qc_test_specimens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qc_test_set_id')->constrained('mm_qc_test_sets')->onDelete('cascade');
                $table->foreignId('qc_test_id')->constrained('mm_qc_tests')->onDelete('cascade');
                $table->integer('specimen_index'); // 1 to 9 continuous serial matching S. NO.
                $table->integer('set_specimen_index')->default(1); // 1 to 3
                $table->string('identification_mark', 100)->nullable();
                $table->decimal('weight_kg', 10, 3)->nullable();
                $table->decimal('load_kn', 10, 2)->nullable();
                $table->decimal('cross_sectional_area', 12, 4)->default(22500); // 150x150 mm = 22500 mm²
                $table->decimal('density_kg_m3', 10, 2)->nullable();
                $table->decimal('strength_mpa', 10, 2)->nullable();
                $table->string('failure_type', 50)->default('Normal');
                $table->string('status', 30)->default('pending'); // pending, pass, fail, error
                $table->string('calculation_error', 100)->nullable();
                $table->timestamps();

                $table->index(['qc_test_id', 'specimen_index']);
                $table->index(['qc_test_set_id', 'set_specimen_index']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_qc_test_specimens');
        Schema::dropIfExists('mm_qc_test_sets');
    }
};
