<?php

/*
Author: Cipher
Created: 2026-11-02 11:26:14 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Batch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchMoistureService
{
    public static function rules(int $plantId): array
    {
        return [
            'moisture_contents' => ['sometimes', 'array', 'max:100'],
            'moisture_contents.*' => ['array:product_id,design_moisture_percentage,consumption_moisture_percentage,measured_at,notes'],
            'moisture_contents.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('mm_products', 'id')
                ->where(fn ($query) => $query->where('plant_id', $plantId)->whereNull('deleted_at'))],
            'moisture_contents.*.design_moisture_percentage' => ['nullable', 'required_without:moisture_contents.*.consumption_moisture_percentage', 'numeric', 'between:0,100', 'decimal:0,3'],
            'moisture_contents.*.consumption_moisture_percentage' => ['nullable', 'required_without:moisture_contents.*.design_moisture_percentage', 'numeric', 'between:0,100', 'decimal:0,3'],
            'moisture_contents.*.measured_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'moisture_contents.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function sync(Batch $batch, array $readings): void
    {
        DB::transaction(function () use ($batch, $readings) {
            // Serialize updates so only one active reading exists per batch/product.
            Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $materials = $batch->materials()->where('plant_id', $batch->plant_id)->reorder()->get()->keyBy('product_id');
            foreach ($readings as $index => $reading) {
                if (! $materials->has($reading['product_id'])) {
                    throw ValidationException::withMessages(["moisture_contents.$index.product_id" => 'Select a material belonging to this batch.']);
                }
            }

            $existing = $batch->moistureContents()->where('plant_id', $batch->plant_id)->get();
            $byProduct = $existing->keyBy('product_id');
            $retained = [];
            foreach ($readings as $reading) {
                $entry = $byProduct->get($reading['product_id']) ?? $batch->moistureContents()->make([
                    'plant_id' => $batch->plant_id, 'product_id' => $reading['product_id'],
                ]);
                $timeColumn = Schema::hasColumn($entry->getTable(), 'measured_date_time') ? 'measured_date_time' : 'measured_at';
                $values = [
                    'design_moisture_percentage' => $reading['design_moisture_percentage'] ?? 0,
                    'consumption_moisture_percentage' => $reading['consumption_moisture_percentage'] ?? 0,
                    $timeColumn => $reading['measured_at'] ?? $entry->{$timeColumn} ?? now(),
                    'notes' => $reading['notes'] ?? null,
                ];
                if (Schema::hasColumn($entry->getTable(), 'material_name')) {
                    $values['material_name'] = preg_replace('/\s*-\s*Run\s*\d+\s*$/i', '', $materials[$reading['product_id']]->material_name);
                }
                $entry->fill($values);
                if (! $entry->exists || $entry->isDirty()) {
                    $entry->save();
                }
                $retained[] = $entry->id;
            }
            // Use model deletes so deleted_by and the audit observer run for each row.
            $existing->whereNotIn('id', $retained)->each->delete();
        });
    }
}
