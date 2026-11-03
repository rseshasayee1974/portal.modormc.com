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
        $standard = $report['columns'] && !array_diff($report['columns'], JayadhityaPlant2787Driver::MATERIALS);
        foreach ($report['group_spans'] as $group) {
            $columns = array_slice($report['columns'], $offset, $group['span']);
            $offset += $group['span'];
            foreach ($columns as $column) {
                $slots[] = [$column, $report['labels'][$column] ?? $column];
                if ($column === 'MSAND 1' && isset($moisture[$column])) {
                    $slots[] = [$column, 'Moi (%)', true];
                    $group['span']++;
                }
            }
            $groups[] = $group;
        }
        if ($standard) {
            $slots = $groups = [];
            foreach (JayadhityaPlant2787Driver::PRINT_GROUPS as $group => $columns) {
                $start = count($slots);
                if ($group === 'MS / ICE') $slots[] = ['MS', '-'];
                foreach ($columns as $column => $label) {
                    $slots[] = [$column, $report['product_aliases'][$column] ?? $label];
                    if ($column === 'MSAND 1') $slots[] = [$column, 'Moi', true];
                }
                if ($group === 'Water') $slots[] = ['correction', ''];
                $groups[] = ['name' => $group, 'span' => count($slots) - $start];
            }
        }

        $runs = $report['inferred_runs'] ? [] : $report['runs'];
        $targets = $report['inferred_runs'] ? [] : ($report['recipe_rows'][1] ?? []);
        $row = static function (array $values, string $section) use ($slots, $report, $moisture, $standard): array {
            return array_map(static function (array $slot) use ($values, $report, $moisture, $standard, $section): string {
                $column = $slot[0];

                if ($slot[2] ?? false) {
                    if ($section === 'target') return 'in %';
                    return ($section === 'actual' || $section === 'load') && isset($moisture[$column])
                        ? number_format($moisture[$column], 1, '.', '') : '';
                }
                if ($column === 'correction') return $section === 'target' ? '+/-' : '';
                if ($column === 'MS') return '';

                $value = $values[$column] ?? ($standard ? 0 : null);
                return $value === null ? '-' : number_format($value, $report['precisions'][$column] ?? (str_starts_with($column, 'ADMIX') ? 2 : 0), '.', '');
            }, $slots);
        };
        return $report + [
            'slots' => $slots, 'print_groups' => $groups,
            'target_cells' => $row($targets, 'target'),
            'actual_cells' => array_map(static fn (array $run): array => $row($run, 'actual'), $runs),
            'set_cells' => $row($report['targets'], 'set'),
            'total_cells' => $row($report['actuals'], $report['inferred_runs'] ? 'load' : 'total'),
        ];
    }
}
