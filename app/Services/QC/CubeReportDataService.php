<?php

namespace App\Services\QC;

use App\Models\QC\QcTest;
use Illuminate\Support\Collection;

class CubeReportDataService
{
    /** Include completed ages of this sample and test type, within its plant. */
    public function sampleSets(QcTest $test): Collection
    {
        $tests = $test->sample_id
            ? QcTest::where('sample_id', $test->sample_id)
                ->where('plant_id', $test->plant_id)
                ->where('test_type_id', $test->test_type_id)
                ->with(['sets.specimens', 'measurements', 'results', 'sample'])
                ->orderBy('age_days')->orderBy('id')->get()
            : collect([$test]);

        return $this->completedSets($tests);
    }

    public function completedSets(Collection $tests): Collection
    {
        $groups = collect();
        foreach ($tests as $test) {
            foreach ($this->sets($test) as $set) {
                // A multi-age test may still be pending while an individual set
                // is completed. Legacy measurements use the test's status.
                $status = strtolower((string) ($set->status ?? $test->overall_status));
                if (!in_array($status, ['pass', 'fail', 'completed'], true)
                    || $set->specimens->isEmpty()
                    || $set->specimens->contains(fn ($specimen) => $specimen->strength_mpa === null)) {
                    continue;
                }
                $group = clone $set;
                $group->source_test_no = $test->test_no;
                $groups->push($group);
            }
        }

        return $groups->sortBy(fn ($set) => $set->age_days ?? PHP_INT_MAX)->values()
            ->map(function ($set, $index) {
                $set->set_number = $index + 1;
                return $set;
            });
    }

    /** Resolve both storage formats without modifying laboratory records. */
    public function sets(QcTest $test): Collection
    {
        $test->loadMissing(['sets.specimens', 'measurements', 'results', 'sample']);
        $sets = $test->sets;
        $hasSpecimenValues = $sets->contains(fn ($set) => $set->specimens->contains(
            fn ($specimen) => $specimen->weight_kg !== null || $specimen->load_kn !== null || $specimen->strength_mpa !== null
        ));
        if ($hasSpecimenValues) {
            return $sets;
        }

        // The original concrete entry form stores one JSON observation per cube.
        // Ignore ordinary parameter text; it does not represent a cube specimen.
        $specimens = $test->measurements->sortBy('row_index')->map(function ($measurement) {
            $meta = json_decode($measurement->value_text ?? '', true);
            if (!is_array($meta) || !array_intersect(['weight_kg', 'load_kn', 'strength_mpa'], array_keys($meta))) {
                return null;
            }
            return (object) [
                'specimen_index' => (int) $measurement->row_index + 1,
                'identification_mark' => $meta['ident_mark'] ?? $meta['identification_mark'] ?? '',
                'weight_kg' => $this->numeric($meta['weight_kg'] ?? null),
                'load_kn' => $this->numeric($meta['load_kn'] ?? null),
                'strength_mpa' => $this->numeric($measurement->value_numeric ?? $meta['strength_mpa'] ?? null),
            ];
        })->filter()->values();

        if ($specimens->isEmpty()) {
            return $sets;
        }

        $result = $test->results->first(fn ($result) => isset($result->criteria_snapshot['avg_strength']));
        $strengths = $specimens->pluck('strength_mpa')->filter(fn ($value) => $value !== null);
        $average = $this->numeric($result?->criteria_snapshot['avg_strength'] ?? null);
        if ($average === null && $strengths->isNotEmpty()) {
            $average = round($strengths->avg(), 2);
        }

        // Legacy entries describe one test age. Do not copy these observations
        // into the unrelated age milestones created by the newer configuration.
        $matchingSet = $test->age_days !== null ? $sets->firstWhere('age_days', $test->age_days) : null;
        return collect([(object) [
            'set_number' => 1,
            'age_days' => $test->age_days,
            'casting_date' => $matchingSet?->casting_date ?? $test->sample?->sample_date,
            'testing_date' => $test->test_date,
            'place_of_casting' => $matchingSet?->place_of_casting ?? $test->sample?->source_location,
            'average_strength' => $average,
            'client_sign_name' => $matchingSet?->client_sign_name,
            'qc_sign_name' => $matchingSet?->qc_sign_name,
            'specimens' => $specimens,
        ]]);
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
