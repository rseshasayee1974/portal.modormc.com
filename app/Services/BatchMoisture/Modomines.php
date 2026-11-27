<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 13:16:23 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\BatchMoisture;

use App\Models\Batch;
use App\Services\JayadhityaBatchSheet;
use Illuminate\Validation\ValidationException;

class Modomines
{
    public static function targets(Batch $batch, array $readings): array
    {
        $fail = static function (string $message): never {
            throw ValidationException::withMessages(['moisture_contents' => $message]);
        };
        $inputs = collect($readings)->keyBy('product_id');
        $recipe = $batch->salesOrder?->mixDesign?->items?->groupBy('product_id');
        $groups = $batch->materials()->where('plant_id', $batch->plant_id)
            ->with('product.category.parent')->get()->groupBy('product_id');
        $rates = [];
        $water = null;
        $delta = 0;
        foreach ($groups as $id => $rows) {
            $row = $rows->first();
            $category = $row->product?->category;
            $group = JayadhityaBatchSheet::materialDisplay($row->material_name.' '.$row->product?->title,
                trim($category?->name.' '.$category?->parent?->name))['group'];
            if (!in_array($group, ['Aggregate', 'Water / Ice'], true)) continue;
            $rate = (float) $recipe?->get($id)?->sum('actual_quantity');
            if ($rate === 0.0 && (float) $rows->sum('target_qty') === 0.0) continue;
            if ($rate <= 0) $fail("A positive SSD mix design quantity is required for {$row->material_name}.");
            if ($group === 'Water / Ice') {
                if ($water !== null) $fail('The Modomines formula requires a single water dosing material.');
                $water = $id;
                $rates[$id] = $rate;
                continue;
            }
            $input = $inputs->get($id);
            if (!isset($input['consumption_moisture_percentage'], $input['design_moisture_percentage'])) {
                $fail("Enter field moisture and absorption for {$row->material_name}.");
            }
            $change = $rate * ($input['consumption_moisture_percentage'] - $input['design_moisture_percentage']) / 100;
            $rates[$id] = $rate + $change;
            $delta += $change;
        }
        if ($water === null) $fail('Add the mix design water material to this batch before applying moisture.');
        $rates[$water] -= $delta;
        if ($rates[$water] < 0) $fail('Aggregate moisture exceeds the available mix design water.');

        $size = (float) $batch->batch_size;
        $capacity = (float) $batch->plant->mixer_capacity;
        if ($size <= 0) $fail('Batch volume must be positive.');
        $result = [];
        foreach ($rates as $id => $rate) {
            $rows = $groups[$id];
            $seen = [];
            foreach ($rows as $row) {
                $volume = $size;
                $run = null;
                if (preg_match('/\bRun\s*(\d+)\s*$/i', $row->material_name, $m)) {
                    if ($capacity <= 0) $fail('Set the plant mixer capacity to calculate run targets.');
                    $run = (int) $m[1];
                    $volume = min($capacity, $size - ($run - 1) * $capacity);
                    if ($run < 1 || $volume <= 0 || isset($seen[$run])) $fail('Check the stored run numbers and mixer capacity.');
                    $seen[$run] = true;
                } elseif ($rows->count() !== 1) {
                    $fail('Each material needs one batch total or one row per run.');
                }
                $result[] = ['id' => $row->id, 'product_id' => $id, 'name' => $row->material_name,
                    'run' => $run, 'volume' => round($volume, 3), 'target' => round($rate * $volume, 3),
                    'actual' => (float) $row->actual_qty];
            }
            if ($seen && count($seen) !== (int) ceil(round($size / $capacity, 9))) {
                $fail('The stored runs must cover the batch volume at the configured mixer capacity.');
            }
        }
        return $result;
    }
}
