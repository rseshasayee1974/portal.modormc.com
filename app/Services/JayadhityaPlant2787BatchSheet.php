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
        $slots = $groups = [];
        $offset = 0;
        $moisture = $report['moisture_rows']['consumption'];
        foreach ($report['group_spans'] as $group) {
            $columns = array_slice($report['columns'], $offset, $group['span']);
            $offset += $group['span'];
            foreach ($columns as $column) {
                $slots[] = [$column, $report['labels'][$column] ?? $column];
                if (isset($moisture[$column])) {
                    $slots[] = [$column, 'Moi (%)', true];
                    $group['span']++;
                }
            }
            $groups[] = $group;
        }

        $runs = $report['inferred_runs'] ? [] : $report['runs'];
        $targets = $report['inferred_runs'] ? [] : ($report['recipe_rows'][1] ?? []);
        $row = static function (array $values, bool $showMoisture = false) use ($slots, $report, $moisture): array {
            return array_map(static function (array $slot) use ($values, $report, $moisture, $showMoisture): string {
                $column = $slot[0];

                if ($slot[2] ?? false) {
                    return $showMoisture ? number_format($moisture[$column], 1, '.', '') : '';
                }

                return isset($values[$column])
                    ? number_format($values[$column], $report['precisions'][$column], '.', '')
                    : '-';
            }, $slots);
        };
        return $report + [
            'slots' => $slots, 'print_groups' => $groups,
            'target_cells' => $row($targets),
            'actual_cells' => array_map(static fn (array $run): array => $row($run, true), $runs),
            'set_cells' => $row($report['targets']),
            'total_cells' => $row($report['actuals'], $report['inferred_runs']),
        ];
    }
}
