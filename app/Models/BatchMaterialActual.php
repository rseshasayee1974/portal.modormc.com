<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 16:34:35 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models;

use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchMaterialActual extends Model
{
    use PlantScoping, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_batch_material_actuals';
    protected $fillable = ['batch_id', 'plant_id', 'product_id', 'actual_qty'];
    protected $casts = ['actual_qty' => 'decimal:3'];

    public static function record(Batch $batch, int $productId, float $quantity): void
    {
        // Callers hold the batch write lock. Older installations can have repeated batch/product keys.
        $rows = static::withTrashed()->where('batch_id', $batch->id)->where('product_id', $productId)
            ->orderByDesc('id')->lockForUpdate()->get();
        $active = $rows->reject(fn ($row) => $row->trashed());
        $row = $active->first() ?? $rows->first() ?? new static();
        $row->fill([
            'batch_id' => $batch->id, 'plant_id' => $batch->plant_id,
            'product_id' => $productId, 'actual_qty' => round($quantity, 3),
        ]);
        if ($row->trashed()) {
            $row->deleted_by = null;
            $row->restore();
        } elseif (!$row->exists || $row->isDirty()) {
            $row->save();
        }
        $active->skip(1)->each->delete();
    }
}
