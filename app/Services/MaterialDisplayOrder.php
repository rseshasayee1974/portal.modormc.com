<?php

/*
Author: ragul-onemodo
Created: 2026-10-31 17:23:49 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

class MaterialDisplayOrder
{
    // Table aliases are internal constants supplied by the application's queries.
    public static function mixDesignItem(string $table = 'mm_mix_design_items'): string
    {
        return "{$table}.display_order";
    }

    public static function batchMaterial(string $table = 'mm_batch_materials'): string
    {
        return "COALESCE((SELECT mdi.display_order FROM mm_mix_design_items mdi
            JOIN mm_sales_orders so ON so.mix_design_id = mdi.mix_design_id
                AND so.plant_id = mdi.plant_id
            JOIN mm_batches b ON b.sales_order_id = so.id AND b.plant_id = so.plant_id
            WHERE b.id = {$table}.batch_id AND mdi.product_id = {$table}.product_id
                AND mdi.deleted_at IS NULL
            ORDER BY mdi.display_order ASC, mdi.id ASC LIMIT 1), 4294967296)";
    }
}
