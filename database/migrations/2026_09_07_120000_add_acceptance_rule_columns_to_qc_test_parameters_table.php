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
        Schema::table('qc_test_parameters', function (Blueprint $table) {
            $table->string('rule_type', 50)->nullable()->after('formula');
            $table->decimal('min_value', 12, 4)->nullable()->after('rule_type');
            $table->decimal('max_value', 12, 4)->nullable()->after('min_value');
            $table->decimal('target_value', 12, 4)->nullable()->after('max_value');
            $table->decimal('tolerance', 12, 4)->nullable()->after('target_value');
            $table->string('standard_reference', 150)->nullable()->after('tolerance');
        });

        // Backfill global rules from qc_test_rules if exists
        if (Schema::hasTable('qc_test_rules')) {
            $rules = DB::table('qc_test_rules')
                ->whereNotNull('parameter_id')
                ->whereNull('material_id')
                ->whereNull('deleted_at')
                ->get();

            foreach ($rules as $rule) {
                DB::table('qc_test_parameters')
                    ->where('id', $rule->parameter_id)
                    ->update([
                        'rule_type' => $rule->rule_type,
                        'min_value' => $rule->min_value,
                        'max_value' => $rule->max_value,
                        'target_value' => $rule->target_value,
                        'tolerance' => $rule->tolerance,
                        'standard_reference' => $rule->standard_reference,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_test_parameters', function (Blueprint $table) {
            $table->dropColumn([
                'rule_type',
                'min_value',
                'max_value',
                'target_value',
                'tolerance',
                'standard_reference',
            ]);
        });
    }
};
