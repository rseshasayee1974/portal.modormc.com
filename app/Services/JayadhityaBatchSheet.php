<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 09:25:14 Asia/Calcutta (UTC+05:30)
 */

namespace App\Services;

use App\Models\Batch;

class JayadhityaBatchSheet
{
    private const GROUPS = [
        'Aggregate' => ['MSAND 1', 'MSAND 2', '20MM', '12MM', 'Agg 5', 'Agg 6'],
        'Cement' => ['CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'Cem 4', 'Cem 5'],
        'Water / Ice' => ['WATER', 'WC', 'Wtr 2', 'Ice'],
        'Admixture' => ['ADMIX 1', 'ADMIX 2', 'Adm 3', 'Adm 4'],
        'Silica' => ['Sil'],
    ];

    public static function matches(Batch $batch): bool
    {
        $order = $batch->relationLoaded('workOrder') && ! $batch->relationLoaded('salesOrder')
            ? $batch->workOrder : $batch->salesOrder;
        $plant = $batch->plant ?? $order?->plant;

        return $plant && ((string) $plant->code === '739'
            || preg_match('/jayadhitya/i', (string) $plant->name) === 1);
    }

    public static function data(Batch $batch): array
    {
        $groups = array_fill_keys(array_keys(self::GROUPS), []);
        $targets = $actuals = $runs = $productColumns = $labels = [];
        $columns = $groupSpans = [];
        foreach ($batch->materials as $material) {
            $rawName = trim((string) ($material->material_name ?: $material->product?->title ?: 'Material'));
            preg_match('/\s*[-–—]?\s*Run\s*[-–—]?\s*(\d+)\s*$/iu', $rawName, $runMatch);
            $name = isset($runMatch[0]) ? trim(substr($rawName, 0, -strlen($runMatch[0]))) : $rawName;
            $column = self::column($name);
            $column ??= $name;
            $alias = trim((string) $material->product?->alias);
            if ($alias !== '' || ! isset($labels[$column])) {
                $labels[$column] = $alias !== '' ? $alias : $name;
            }
            $group = self::group($column, (string) $material->product?->category?->name);
            if (! in_array($column, $groups[$group], true)) {
                $groups[$group][] = $column;
                $columns[] = $column;
                $last = array_key_last($groupSpans);
                if ($last !== null && $groupSpans[$last]['name'] === $group) {
                    $groupSpans[$last]['span']++;
                } else {
                    $groupSpans[] = ['name' => $group, 'span' => 1];
                }
            }
            $target = (float) $material->target_qty;
            $actual = (float) $material->actual_qty;
            if ($material->product_id) {
                $productColumns[$material->product_id] = $column;
            }
            $targets[$column] = ($targets[$column] ?? 0) + $target;
            $actuals[$column] = ($actuals[$column] ?? 0) + $actual;
            $run = $runMatch[1] ?? 'Stored totals';
            $runs[$run][$column] = ($runs[$run][$column] ?? 0) + $actual;
        }
        uksort($runs, 'strnatcmp');
        $inferredRuns = count($runs) === 1 && isset($runs['Stored totals']);
        $runSizes = [];
        if ($inferredRuns) {
            // Match the reconciliation editor's legacy-total split, including its 1.25 m3 fallback.
            $quantity = (float) $batch->batch_size;
            $capacity = (float) ($batch->plant?->mixer_capacity ?: 1.25);
            $capacity = $capacity > 0 ? $capacity : 1.25;
            $count = max(1, (int) ceil($quantity / $capacity));
            $runs = [];
            for ($index = 0; $index < $count; $index++) {
                $volume = min($capacity, max(0, $quantity - $index * $capacity));
                $runSizes[] = $volume;
                foreach ($actuals as $column => $actual) {
                    $runs[$index + 1][$column] = $quantity > 0 ? round($actual * $volume / $quantity, 3) : $actual;
                }
            }
        }
        $totalTarget = array_sum($targets);
        $totalActual = array_sum($actuals);
        $order = $batch->relationLoaded('workOrder') && ! $batch->relationLoaded('salesOrder')
            ? $batch->workOrder : $batch->salesOrder;
        $recipe = [];
        foreach ($order?->mixDesign?->items ?? [] as $item) {
            $column = $productColumns[$item->product_id] ?? self::column((string) $item->product?->title);
            if ($column !== null && in_array($column, array_merge(...array_values($groups)), true)) {
                $recipe[$column] = ($recipe[$column] ?? 0) + (float) $item->actual_quantity;
            }
        }

        return [
            'groups' => array_filter($groups),
            'columns' => $columns,
            'group_spans' => $groupSpans,
            'labels' => $labels,
            'targets' => $targets,
            'actuals' => $actuals,
            'runs' => $runs,
            'inferred_runs' => $inferredRuns,
            'run_sizes' => $runSizes,
            'recipe' => $recipe,
            'total_target' => $totalTarget,
            'total_actual' => $totalActual,
            'difference' => $totalTarget != 0 ? ($totalActual - $totalTarget) / $totalTarget * 100 : 0,
        ];
    }

    public static function layoutData(Batch $batch): array
    {
        $data = self::data($batch);
        $numberedRuns = ! $data['inferred_runs'] && $data['runs'] && ! array_key_exists('Stored totals', $data['runs']);
        $runCount = $numberedRuns ? count($data['runs']) : 0;
        // Material columns and run rows follow stored consumption, without fixed slot limits.
        $data['runs'] = array_values($data['runs']);
        $quantity = (float) $batch->batch_size;
        // With numbered runs, show the average mixer-run size separately from load volume.
        $data['run_size'] = $data['run_sizes'][0] ?? ($runCount > 0 ? $quantity / $runCount : $quantity);
        $data['recipe_rows'] = [
            $data['recipe'] ?: null,
            $runCount > 0 ? array_map(function ($target, $column) use ($runCount) {
                $precision = in_array($column, ['ADMIX 1', 'ADMIX 2', 'Adm 3', 'Adm 4', 'Sil'], true) ? 2 : 0;
                return round($target / $runCount, $precision);
            }, $data['targets'], array_keys($data['targets'])) : ($quantity > 0 ? array_map(fn ($target) => $target / $quantity, $data['targets']) : null),
        ];
        if ($runCount > 0) $data['recipe_rows'][1] = array_combine(array_keys($data['targets']), $data['recipe_rows'][1]);
        $data['with_this_load'] = $batch->exists && $batch->sales_order_id && $batch->plant_id
            ? Batch::withoutPlantScope()->where('plant_id', $batch->plant_id)
                ->where('sales_order_id', $batch->sales_order_id)
                ->where('id', '<=', $batch->id)
                ->whereIn('status', [Batch::STATUS_LOADING, Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])
                ->sum('batch_size')
            : null;

        return $data;
    }

    private static function group(string $column, string $category): string
    {
        foreach (self::GROUPS as $group => $columns) {
            if (in_array($column, $columns, true)) {
                return $group;
            }
        }
        $name = strtoupper($category.' '.$column);

        return match (true) {
            str_contains($name, 'SIL'), str_contains($name, 'FUME') => 'Silica',
            str_contains($name, 'CEM'), str_contains($name, 'FLY'), str_contains($name, 'GGBS') => 'Cement',
            str_contains($name, 'WATER'), str_contains($name, 'ICE'), str_contains($name, 'WTR') => 'Water / Ice',
            str_contains($name, 'ADM'), str_contains($name, 'CHEM') => 'Admixture',
            default => 'Aggregate',
        };
    }

    private static function column(string $name): ?string
    {
        $key = strtoupper(preg_replace('/[^a-z0-9]/i', '', $name));
        $aliases = [
            'MSAND' => 'MSAND 1', 'MSAND1' => 'MSAND 1', 'MSA1' => 'MSAND 1',
            'MSAND2' => 'MSAND 2', 'MSA2' => 'MSAND 2', '20M' => '20MM', '12M' => '12MM',
            'CEMENT' => 'CEMENT 1', 'CEM1' => 'CEMENT 1', 'CEM2' => 'CEMENT 2',
            'CEM3' => 'CEMENT 3', 'CEMENT4' => 'Cem 4', 'CEMENT5' => 'Cem 5',
            'WAT' => 'WATER', 'WTR' => 'WATER', 'WTR1' => 'WATER', 'WATER1' => 'WATER',
            'ADM1' => 'ADMIX 1', 'ADMI1' => 'ADMIX 1', 'ADMIX' => 'ADMIX 1',
            'ADM2' => 'ADMIX 2', 'ADMI2' => 'ADMIX 2', 'ADMIX3' => 'Adm 3',
            'ADMIX4' => 'Adm 4', 'SILICA' => 'Sil',
        ];
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }
        foreach (self::GROUPS as $columns) {
            foreach ($columns as $column) {
                if (strtoupper(preg_replace('/[^a-z0-9]/i', '', $column)) === $key) {
                    return $column;
                }
            }
        }

        return null;
    }
}
