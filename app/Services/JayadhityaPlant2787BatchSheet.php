<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:46:39 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Batch;
use App\Models\CustomSetting;
use App\Services\BatchSheet\Drivers\JayadhityaPlant2787Driver;
use Illuminate\Support\Facades\Schema;

class JayadhityaPlant2787BatchSheet
{
    public static function matches(Batch $batch): bool
    {
        $plant = $batch->plant ?? $batch->salesOrder?->plant;
        $plantId = $batch->plant_id ?: $plant?->id;
        $driver = $plantId && Schema::hasTable('mm_custom_settings')
            ? (CustomSetting::getForModule($plantId, 'batching')['batch_sheet_driver'] ?? '') : '';
        return $driver === JayadhityaPlant2787Driver::CODE || ($driver === '' && (string) $plant?->code === '2787');
    }

    public static function data(Batch $batch): array
    {
        $report = JayadhityaBatchSheet::layoutData($batch);
        $slots = [
            ['AGG1', '20 M'], ['MSAND 1', 'MSAN'], [null, 'Moi'], ['12MM', '12 MM'], ['20MM', '20 MM'], ['Agg 6', '0'],
            ['CEMENT 1', 'CEM1'], ['CEMENT 2', 'CEM2'], ['CEMENT 3', 'CEM3'], ['WATER', 'WATER'],
            [null, ''], ['MS', '-'], ['Ice', ''], ['ADMIX 1', 'ADMIX1'], ['ADMIX 2', '-'], [null, ''],
        ];
        $groups = [['name' => 'Aggregate', 'span' => 6], ['name' => 'Cement', 'span' => 3],
            ['name' => 'Water', 'span' => 2], ['name' => 'MS / ICE', 'span' => 2], ['name' => 'Admixture', 'span' => 3]];
        $known = array_filter(array_column($slots, 0));
        foreach (array_diff($report['columns'], $known) as $column) {
            $slots[] = [$column, $report['labels'][$column] ?? $column];
            $groups[] = ['name' => JayadhityaBatchSheet::materialDisplay($column)['group'], 'span' => 1];
        }
        // Never fabricate equal cycle readings from a stored load total.
        $runs = $report['inferred_runs'] ? [$report['actuals']] : $report['runs'];
        $count = count($runs);
        $targets = !$report['inferred_runs'] && $count > 0
            ? array_map(fn ($value) => $value / $count, $report['targets']) : ($report['recipe'] ?: []);
        $row = static function (array $values, string $kind) use ($slots, $report): array {
            $cells = [];
            foreach ($slots as $index => [$column, $label]) {
                if ($index === 2) {
                    $cells[] = $kind === 'target' ? 'in %' : ($kind === 'actual'
                        ? number_format($report['moisture_rows']['consumption']['MSAND 1'] ?? 0, 1, '.', '') : '');
                } elseif ($index === 10) {
                    $cells[] = $kind === 'target' ? '+/-' : ($kind === 'actual' ? '0' : '');
                } elseif ($column === null) {
                    $cells[] = '';
                } else {
                    $precision = in_array($column, ['ADMIX 1', 'ADMIX 2'], true) ? 2 : ($report['precisions'][$column] ?? 0);
                    $cells[] = number_format((float) ($values[$column] ?? 0), $precision, '.', '');
                }
            }
            return $cells;
        };
        return $report + [
            'slots' => $slots, 'print_groups' => $groups,
            'target_cells' => $row($targets, 'target'),
            'actual_cells' => array_map(fn ($values) => $row($values, 'actual'), $runs),
            'set_cells' => $row($report['targets'], 'total'), 'total_cells' => $row($report['actuals'], 'total'),
        ];
    }
}
