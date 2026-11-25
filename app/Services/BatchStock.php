<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 16:37:12 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Batch;
use App\Models\CustomSetting;
use App\Models\Product;
use App\Models\Quantity;
use Illuminate\Validation\ValidationException;

final class BatchStock
{
    private static function consumes(int $status): bool
    {
        return in_array($status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED], true);
    }

    private static function enabled(Batch $batch): bool
    {
        $settings = CustomSetting::getForModule($batch->plant_id, 'batching');
        return filter_var($settings['with_inventory'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    // Stock balances have two decimal places. Sum mixer runs before rounding each product/UOM.
    private static function quantities(Batch $batch): array
    {
        $rows = $batch->materials()->where('plant_id', $batch->plant_id)->reorder()
            ->whereNotNull('product_id')->where('actual_qty', '>', 0)
            ->selectRaw('product_id, uom_id, SUM(actual_qty) AS consumed')->groupBy('product_id', 'uom_id')->get();
        $quantities = [];
        foreach ($rows as $row) {
            $units = (int) round(100 * (float) $row->consumed);
            if ($units > 0) $quantities[$row->product_id.':'.$row->uom_id] = $units;
        }
        ksort($quantities);
        return $quantities;
    }

    // Capture before replacing material rows. Legacy batches used status and the plant setting.
    public static function previous(Batch $batch): array
    {
        return $batch->posted_stock ?? (self::consumes((int) $batch->status) && self::enabled($batch)
            ? self::quantities($batch) : []);
    }

    // Caller holds the batch lock (or just inserted the batch) within the same transaction.
    public static function reconcile(Batch $batch, array $previous, int $status): void
    {
        $next = self::consumes($status) && ($previous || self::enabled($batch)) ? self::quantities($batch) : [];
        $keys = array_keys($previous + $next);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $delta = ($previous[$key] ?? 0) - ($next[$key] ?? 0);
            if ($delta === 0) continue;
            [$product, $unit] = explode(':', $key);
            $identity = ['plant_id' => $batch->plant_id, 'product_id' => (int) $product, 'uom_id' => $unit === '' ? null : (int) $unit, 'is_warehouse' => false];
            $balance = Quantity::where($identity)->orderByDesc('id')->lockForUpdate()->first();
            $available = (int) round((float) ($balance?->quantity ?? 0) * 100);
            if ($available + $delta < 0) {
                $title = Product::where('plant_id', $batch->plant_id)->whereKey($product)->value('title') ?? "Product #$product";
                throw ValidationException::withMessages(['materials' => sprintf(
                    'Insufficient stock for %s. Available: %.2f, additional quantity required: %.2f.',
                    $title, $available / 100, -$delta / 100
                )]);
            }
            $balance ??= new Quantity($identity + ['opening_quantity' => 0, 'status' => 1, 'created_by' => auth()->id()]);
            $balance->quantity = ($available + $delta) / 100;
            $balance->updated_by = auth()->id();
            $balance->save();
        }
        if ($batch->posted_stock !== $next) {
            $batch->posted_stock = $next;
            $batch->saveQuietly();
        }
    }
}
