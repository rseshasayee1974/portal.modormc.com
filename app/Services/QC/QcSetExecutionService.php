<?php

namespace App\Services\QC;

use App\Models\QC\QcTest;
use App\Models\QC\QcTestSet;
use App\Models\QC\QcTestSpecimen;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class QcSetExecutionService
{
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

            $calculator = app(ConcreteCubeCalculator::class);
            $dimensions = $calculator->dimensions($test);
            $calculator->snapshot($test, $dimensions);
            $setsData = $payload['sets'] ?? [];

            foreach ($setsData as $sData) {
                $setId = $sData['id'] ?? null;
                $testSet = $setId ? QcTestSet::where('qc_test_id', $test->id)->find($setId) : null;
                if (!$testSet) {
                    throw ValidationException::withMessages(['sets' => 'Select a set belonging to this test.']);
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

                $casting = Carbon::parse($testSet->casting_date ?: $test->sample->sample_date)->startOfDay();
                $testing = Carbon::parse($sData['testing_date'] ?? $payload['test_date'] ?? $test->test_date)->startOfDay();
                if ($testing->lt($casting)) {
                    throw ValidationException::withMessages(['sets' => 'Testing date cannot be before casting date.']);
                }
                $testSet->testing_date = $testing;
                $testSet->age_days = (int) $casting->diffInDays($testing);

                // Process specimens in this set
                $specimensData = $sData['specimens'] ?? [];

                foreach ($specimensData as $specData) {
                    $specId = $specData['id'] ?? null;
                    $specimen = $specId ? QcTestSpecimen::where('qc_test_set_id', $testSet->id)->find($specId) : null;
                    if (!$specimen) {
                        throw ValidationException::withMessages(['sets' => 'Select specimens belonging to the selected set.']);
                    }

                    $values = $calculator->observation(array_replace([
                        'weight_kg' => $specimen->weight_kg, 'load_kn' => $specimen->load_kn,
                    ], $specData), $dimensions);
                    $specimen->fill($values);
                    $specimen->identification_mark = $specData['identification_mark'] ?? $specimen->identification_mark;
                    $specimen->failure_type = $specData['failure_type'] ?? 'Normal';
                    $specimen->calculation_error = null;
                    $specimen->status = $values['weight_kg'] > 0 && $values['load_kn'] > 0 ? 'completed' : 'pending';
                    $specimen->save();
                }

                $stored = $testSet->specimens()->get();
                $complete = $stored->count() >= 3 && $stored->every(fn ($specimen) => $specimen->weight_kg > 0 && $specimen->load_kn > 0 && $specimen->strength_mpa !== null);
                $testSet->average_strength = $complete ? round($stored->avg('strength_mpa'), 2) : null;
                $testSet->status = 'pending';
                $testSet->error_reason = null;
                if ($complete) {
                    $minimum = $testSet->min_strength ?? $testSet->target_strength;
                    $testSet->status = $minimum === null ? 'completed' : ($testSet->average_strength >= $minimum ? 'pass' : 'fail');
                    $outlier = $stored->contains(fn ($specimen) => abs((float) $specimen->strength_mpa - (float) $testSet->average_strength) > (float) $testSet->average_strength * 0.15 + 0.000001);
                    if ($outlier) {
                        $testSet->status = 'retest';
                        $testSet->error_reason = 'Specimen strength differs from the set average by more than 15%.';
                    }
                }

                $testSet->save();
            }

            $statuses = $test->sets()->pluck('status');
            $test->overall_status = $statuses->contains(fn ($status) => !in_array($status, ['pass', 'fail', 'completed', 'retest'], true)) ? 'pending'
                : ($statuses->contains('retest') ? 'retest' : ($statuses->contains('fail') ? 'fail' : 'pass'));
            $test->evaluated_at = $test->overall_status === 'pending' ? null : now();
            $test->test_date = $test->sets()->whereIn('status', ['pass', 'fail', 'completed', 'retest'])->max('testing_date');
            $test->save();

            return $test;
        });
    }
}
