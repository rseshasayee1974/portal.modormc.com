<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map of old table names to new mm_ prefixed names.
     */
    protected array $tableRenames = [
        'notification_emails'   => 'mm_notification_emails',
        'qc_material_tests'     => 'mm_qc_material_tests',
        'qc_samples'            => 'mm_qc_samples',
        'qc_tests'              => 'mm_qc_tests',
        'qc_test_measurements'  => 'mm_qc_test_measurements',
        'qc_test_parameters'    => 'mm_qc_test_parameters',
        'qc_test_results'       => 'mm_qc_test_results',
        'qc_test_rules'         => 'mm_qc_test_rules',
        'qc_test_schedules'     => 'mm_qc_test_schedules',
        'qc_test_types'         => 'mm_qc_test_types',
        'qc_units'              => 'mm_qc_units',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tableRenames as $oldName => $newName) {
            if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
                Schema::rename($oldName, $newName);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tableRenames as $oldName => $newName) {
            if (Schema::hasTable($newName) && !Schema::hasTable($oldName)) {
                Schema::rename($newName, $oldName);
            }
        }
    }
};
