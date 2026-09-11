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
        // 1. Add qc_unit_id to mm_qc_test_parameters
        if (Schema::hasTable('mm_qc_test_parameters')) {
            Schema::table('mm_qc_test_parameters', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_qc_test_parameters', 'qc_unit_id')) {
                    $table->unsignedBigInteger('qc_unit_id')->nullable()->after('data_type');
                    $table->index('qc_unit_id');
                }
            });

            // Populate qc_unit_id from existing units in mm_qc_units or seed standard units
            if (Schema::hasTable('mm_qc_units')) {
                $unitsToEnsure = [
                    ['code' => 'MPA', 'symbol' => 'MPa', 'name' => 'Megapascal', 'dimension' => 'Pressure/Strength'],
                    ['code' => 'KN', 'symbol' => 'kN', 'name' => 'Kilonewton', 'dimension' => 'Force'],
                    ['code' => 'KG', 'symbol' => 'kg', 'name' => 'Kilogram', 'dimension' => 'Mass'],
                    ['code' => 'G', 'symbol' => 'g', 'name' => 'Gram', 'dimension' => 'Mass'],
                    ['code' => 'MM', 'symbol' => 'mm', 'name' => 'Millimeter', 'dimension' => 'Length'],
                    ['code' => 'MM2', 'symbol' => 'mm²', 'name' => 'Square Millimeter', 'dimension' => 'Area'],
                    ['code' => 'KG_M3', 'symbol' => 'kg/m³', 'name' => 'Kilogram per Cubic Meter', 'dimension' => 'Density'],
                    ['code' => 'PERCENT', 'symbol' => '%', 'name' => 'Percentage', 'dimension' => 'Ratio'],
                    ['code' => 'CELSIUS', 'symbol' => '°C', 'name' => 'Degree Celsius', 'dimension' => 'Temperature'],
                    ['code' => 'MIN', 'symbol' => 'min', 'name' => 'Minute', 'dimension' => 'Time'],
                    ['code' => 'HH_MM', 'symbol' => 'hh:mm', 'name' => 'Time Format', 'dimension' => 'Time'],
                ];

                $now = now();
                foreach ($unitsToEnsure as $u) {
                    $existing = DB::table('mm_qc_units')
                        ->where('code', $u['code'])
                        ->orWhere('symbol', $u['symbol'])
                        ->first();
                    if (!$existing) {
                        DB::table('mm_qc_units')->insert(array_merge($u, [
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]));
                    }
                }

                // Map parameters
                $params = DB::table('mm_qc_test_parameters')->whereNull('deleted_at')->get();
                foreach ($params as $p) {
                    if (!empty($p->unit)) {
                        $matchedUnit = DB::table('mm_qc_units')
                            ->where('symbol', trim($p->unit))
                            ->orWhere('code', strtoupper(trim($p->unit)))
                            ->first();

                        if ($matchedUnit) {
                            DB::table('mm_qc_test_parameters')
                                ->where('id', $p->id)
                                ->update(['qc_unit_id' => $matchedUnit->id]);
                        }
                    }
                }
            }
        }

        // 2. Add config_id and configuration_snapshot to mm_qc_tests
        if (Schema::hasTable('mm_qc_tests')) {
            Schema::table('mm_qc_tests', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_qc_tests', 'config_id')) {
                    $table->unsignedBigInteger('config_id')->nullable()->after('test_type_id');
                    $table->index('config_id');
                }
                if (!Schema::hasColumn('mm_qc_tests', 'configuration_snapshot')) {
                    $table->json('configuration_snapshot')->nullable()->after('remarks');
                }
            });
        }

        // 3. Add qc_test_set_id and qc_test_specimen_id to mm_qc_test_measurements
        if (Schema::hasTable('mm_qc_test_measurements')) {
            Schema::table('mm_qc_test_measurements', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_qc_test_measurements', 'qc_test_set_id')) {
                    $table->unsignedBigInteger('qc_test_set_id')->nullable()->after('qc_test_id');
                    $table->index('qc_test_set_id');
                }
                if (!Schema::hasColumn('mm_qc_test_measurements', 'qc_test_specimen_id')) {
                    $table->unsignedBigInteger('qc_test_specimen_id')->nullable()->after('qc_test_set_id');
                    $table->index('qc_test_specimen_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_qc_test_measurements')) {
            Schema::table('mm_qc_test_measurements', function (Blueprint $table) {
                if (Schema::hasColumn('mm_qc_test_measurements', 'qc_test_specimen_id')) {
                    $table->dropColumn('qc_test_specimen_id');
                }
                if (Schema::hasColumn('mm_qc_test_measurements', 'qc_test_set_id')) {
                    $table->dropColumn('qc_test_set_id');
                }
            });
        }

        if (Schema::hasTable('mm_qc_tests')) {
            Schema::table('mm_qc_tests', function (Blueprint $table) {
                if (Schema::hasColumn('mm_qc_tests', 'configuration_snapshot')) {
                    $table->dropColumn('configuration_snapshot');
                }
                if (Schema::hasColumn('mm_qc_tests', 'config_id')) {
                    $table->dropColumn('config_id');
                }
            });
        }

        if (Schema::hasTable('mm_qc_test_parameters')) {
            Schema::table('mm_qc_test_parameters', function (Blueprint $table) {
                if (Schema::hasColumn('mm_qc_test_parameters', 'qc_unit_id')) {
                    $table->dropColumn('qc_unit_id');
                }
            });
        }
    }
};
