<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 09:25:14 Asia/Calcutta (UTC+05:30)
 */

namespace App\Services;

use App\Models\Batch;
use Illuminate\Validation\ValidationException;

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
        $groups = self::GROUPS;
        $targets = $actuals = $runs = $productColumns = [];
        foreach ($batch->materials as $material) {
            $rawName = trim((string) ($material->material_name ?: $material->product?->title ?: 'Material'));
            preg_match('/\s*[-–—]?\s*Run\s*[-–—]?\s*(\d+)\s*$/iu', $rawName, $runMatch);
            $name = isset($runMatch[0]) ? trim(substr($rawName, 0, -strlen($runMatch[0]))) : $rawName;
            $column = self::column($name);
            if ($column === null) {
                // Keep unmapped materials visible instead of silently dropping their weights.
                $column = $name;
                $category = strtoupper((string) $material->product?->category?->name);
                $group = match (true) {
                    str_contains($category, 'SIL') => 'Silica',
                    str_contains($category, 'CEM'), str_contains($category, 'FLY'), str_contains($category, 'GGBS') => 'Cement',
                    str_contains($category, 'WATER'), str_contains($category, 'ICE') => 'Water / Ice',
                    str_contains($category, 'ADM'), str_contains($category, 'CHEM') => 'Admixture',
                    default => 'Aggregate',
                };
                if (! in_array($column, $groups[$group], true)) {
                    $groups[$group][] = $column;
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
            'groups' => $groups,
            'columns' => array_merge(...array_values($groups)),
            'targets' => $targets,
            'actuals' => $actuals,
            'runs' => $runs,
            'recipe' => $recipe,
            'total_target' => $totalTarget,
            'total_actual' => $totalActual,
            'difference' => $totalTarget != 0 ? ($totalActual - $totalTarget) / $totalTarget * 100 : 0,
        ];
    }

    public static function layoutData(Batch $batch): array
    {
        $data = self::data($batch);
        $labels = [];
        $mapping = [];
        foreach (self::GROUPS as $group => $slots) {
            $extras = array_diff($data['groups'][$group], $slots);
            $used = array_merge(array_keys($data['targets']), array_keys($data['recipe']));
            $available = array_values(array_diff($slots, $used));
            if (count($extras) > count($available)) {
                throw ValidationException::withMessages([
                    'materials' => "The fixed batch sheet has no free {$group} columns. Map the material names to its existing columns before exporting.",
                ]);
            }
            foreach (array_values($extras) as $index => $name) {
                $mapping[$name] = $available[$index];
                $labels[$available[$index]] = $name;
            }
        }
        $remap = function (array $row) use ($mapping): array {
            $result = [];
            foreach ($row as $column => $value) {
                $key = $mapping[$column] ?? $column;
                $result[$key] = ($result[$key] ?? 0) + $value;
            }

            return $result;
        };
        foreach (['targets', 'actuals', 'recipe'] as $key) {
            $data[$key] = $remap($data[$key]);
        }
        $data['runs'] = array_values(array_map($remap, $data['runs']));
        // Always retain both actual rows, even when only load totals were stored.
        while (count($data['runs']) < 2) {
            $data['runs'][] = null;
        }
        $quantity = (float) $batch->batch_size;
        $data['recipe_rows'] = [
            $data['recipe'] ?: null,
            $quantity > 0 ? array_map(fn ($target) => $target / $quantity, $data['targets']) : null,
        ];
        // These two six-cell rows exist in the source layout. Unknown readings stay unknown.
        $data['tolerance_rows'] = [array_fill(0, 6, null), array_fill(0, 6, null)];
        $data['groups'] = self::GROUPS;
        $data['columns'] = array_merge(...array_values(self::GROUPS));
        $data['labels'] = $labels;

        return $data;
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
