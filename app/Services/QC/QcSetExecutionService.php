<?php

namespace App\Services\QC;

use App\Models\QC\QcTest;
use App\Models\QC\QcTestSet;
use App\Models\QC\QcTestSpecimen;
use App\Models\QC\QcTestParameter;
use App\Models\QC\QcTestMeasurement;
use App\Models\QC\QcTestResult;
use Illuminate\Support\Facades\DB;

class QcSetExecutionService
{
    protected FormulaEngine $formulaEngine;

    public function __construct(FormulaEngine $formulaEngine)
    {
        $this->formulaEngine = $formulaEngine;
    }

    /**
     * Process and evaluate execution data for a multi-set test (e.g. 3 sets of 3 concrete cubes).
     */
    public function processExecution(QcTest $test, array $payload): QcTest
    {
        return DB::transaction(function () use ($test, $payload) {
            // 1. Snapshot configuration if not already locked
            if (empty($test->configuration_snapshot) && $test->config) {
                $test->configuration_snapshot = $test->config->generateConfigurationSnapshot();
            }

            $test->test_date = $payload['test_date'] ?? $test->test_date ?? now();
            $test->remarks = $payload['remarks'] ?? $test->remarks;
            $test->tested_by = auth()->id() ?: ($test->tested_by ?: 1);

            $setsData = $payload['sets'] ?? [];
            $allSetsEvaluated = true;
            $anySetFailed = false;

            foreach ($setsData as $sData) {
                $setId = $sData['id'] ?? null;
                $testSet = $setId ? QcTestSet::where('qc_test_id', $test->id)->find($setId) : null;
                if (!$testSet) {
                    continue;
                }

                // Update set level metadata
                if (isset($sData['casting_date'])) {
                    $testSet->casting_date = $sData['casting_date'];
                }
                if (isset($sData['place_of_casting'])) {
                    $testSet->place_of_casting = $sData['place_of_casting'];
                }
                if (isset($sData['testing_date'])) {
                    $testSet->testing_date = $sData['testing_date'];
                }
                if (isset($sData['age_days'])) {
                    $testSet->age_days = (int)$sData['age_days'];
                }
                if (isset($sData['client_sign_name'])) {
                    $testSet->client_sign_name = $sData['client_sign_name'];
                    if (!empty($sData['client_sign_name']) && !$testSet->client_signed_at) {
                        $testSet->client_signed_at = now();
                    }
                }
                if (isset($sData['qc_sign_name'])) {
                    $testSet->qc_sign_name = $sData['qc_sign_name'];
                    if (!empty($sData['qc_sign_name']) && !$testSet->qc_signed_at) {
                        $testSet->qc_signed_at = now();
                    }
                }

                $area = (float)($test->config?->specimen_dimensions ? 22500 : 22500);
                $volumeM3 = 0.003375; // 150mm cube volume in m3

                // Process specimens in this set
                $specimensData = $sData['specimens'] ?? [];
                $validStrengths = [];

                foreach ($specimensData as $specData) {
                    $specId = $specData['id'] ?? null;
                    $specimen = $specId ? QcTestSpecimen::where('qc_test_set_id', $testSet->id)->find($specId) : null;
                    if (!$specimen) {
                        continue;
                    }

                    $weightKg = isset($specData['weight_kg']) && $specData['weight_kg'] !== '' ? (float)$specData['weight_kg'] : null;
                    $loadKn = isset($specData['load_kn']) && $specData['load_kn'] !== '' ? (float)$specData['load_kn'] : null;
                    $identMark = $specData['identification_mark'] ?? $specimen->identification_mark;
                    $failureType = $specData['failure_type'] ?? 'Normal';

                    $specimen->identification_mark = $identMark;
                    $specimen->weight_kg = $weightKg;
                    $specimen->load_kn = $loadKn;
                    $specimen->cross_sectional_area = $area;
                    $specimen->failure_type = $failureType;

                    // Density calculation: weight / volume
                    if ($weightKg !== null && $volumeM3 > 0) {
                        $specimen->density_kg_m3 = round($weightKg / $volumeM3, 2);
                    } else {
                        $specimen->density_kg_m3 = null;
                    }

                    // Strength calculation: (load_kn * 1000) / area
                    if ($loadKn !== null && $area > 0) {
                        $calcResult = $this->formulaEngine->evaluateFormulaDetailed('(LOAD * 1000) / AREA', [
                            'LOAD' => $loadKn,
                            'AREA' => $area,
                        ]);

                        if ($calcResult['status'] === FormulaEngine::STATUS_OK) {
                            $specimen->strength_mpa = round($calcResult['value'], 2);
                            $specimen->calculation_error = null;
                            $specimen->status = 'completed';
                            $validStrengths[] = $specimen->strength_mpa;
                        } else {
                            $specimen->strength_mpa = null;
                            $specimen->calculation_error = $calcResult['error'] ?? 'ERR_CALC';
                            $specimen->status = 'error';
                        }
                    } else {
                        $specimen->strength_mpa = null;
                        $specimen->calculation_error = null;
                        $specimen->status = 'pending';
                    }

                    $specimen->save();
                }

                // Recalculate set average strength
                if (!empty($validStrengths)) {
                    $setAvg = round(array_sum($validStrengths) / count($validStrengths), 2);
                    $testSet->average_strength = $setAvg;

                    // Evaluate acceptance rule strictly (do not infer from target %)
                    if ($testSet->min_strength !== null) {
                        $rule = [
                            'rule_type' => QcTestParameter::RULE_TYPE_GREATER_THAN_OR_EQUAL,
                            'min_value' => $testSet->min_strength,
                        ];
                        $eval = $this->formulaEngine->evaluateRule($rule, $setAvg);
                        $testSet->status = strtolower($eval); // 'pass' or 'fail'
                        if ($eval === 'FAIL') {
                            $anySetFailed = true;
                        }
                    } else {
                        $testSet->status = 'completed';
                    }
                } else {
                    $testSet->average_strength = null;
                    $testSet->status = 'pending';
                    $allSetsEvaluated = false;
                }

                $testSet->save();
            }

            // Overall test status
            if ($anySetFailed) {
                $test->overall_status = 'fail';
            } elseif ($allSetsEvaluated && count($setsData) > 0) {
                $test->overall_status = 'pass';
            } else {
                $test->overall_status = 'pending';
            }

            $test->evaluated_at = now();
            $test->save();

            return $test;
        });
    }
}
