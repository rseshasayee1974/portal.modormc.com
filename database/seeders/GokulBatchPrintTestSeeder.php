<?php
/*
Author: Cipher
Created: 2026-10-31 14:06:19 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Models\Batch;
use App\Services\BatchSheetBrowserPdf;
use App\Services\JayadhityaBatchSheet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GokulBatchPrintTestSeeder extends Seeder
{
    public function run(int $plantId = 1): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('This print fixture is restricted to local/testing databases.');
        }

        $batchId = DB::transaction(function () use ($plantId) {
            $plant = DB::table('mm_plants')->where('id', $plantId)->whereNull('deleted_at')->first();
            if (! $plant) throw new \RuntimeException("Plant {$plantId} does not exist.");
            $unitId = DB::table('mm_product_units')->whereNull('deleted_at')->whereIn('unit_code', ['KGS', 'KG'])->value('id');
            if (! $unitId) throw new \RuntimeException('A kilogram unit is required.');
            // Query-builder inserts intentionally avoid inventory, billing and notification observers.
            $record = function (string $table, array $key, array $data): int {
                $existing = DB::table($table)->where($key)->whereNull('deleted_at')->first();
                if ($existing) return (int) $existing->id;
                return DB::table($table)->insertGetId($key + $data + ['created_at' => now(), 'updated_at' => now()]);
            };
            $customerId = $record('mm_patrons', ['plant_id' => $plantId, 'code' => 'GOKUL-PRINT-CUSTOMER'], [
                'legal_name' => 'S N DHAMANI', 'patron_type' => json_encode(['Customer']),
                'status' => 1, 'operational_status' => 'active',
            ]);
            $siteId = $record('mm_sites', ['plant_id' => $plantId, 'name' => 'poochiathipedu'], [
                'patron_id' => json_encode([$customerId]), 'type' => 'unloading', 'status' => 'Active',
            ]);
            $driverId = $record('mm_personnels', ['plant_id' => $plantId, 'employee_code' => 'GOKUL-PRINT-DRIVER'], [
                'first_name' => 'AJIJUR', 'last_name' => 'RAHAMN', 'status' => 'Active',
            ]);
            $truckId = $record('mm_machines', ['plant_id' => $plantId, 'registration' => 'TN18BJ5233'], []);
            $mixId = $record('mm_mix_designs', ['plant_id' => $plantId, 'design_code' => 'M25 S N DHAMANI'], [
                'design_name' => 'M25', 'is_active' => 1,
            ]);
            $orderId = $record('mm_sales_orders', ['plant_id' => $plantId, 'prefix' => 'GOKUL-PRINT-TEST-', 'order_no' => 'S N DHAMANI'], [
                'customer_id' => $customerId, 'site_id' => $siteId, 'mix_design_id' => $mixId,
                'total_qty' => 50, 'produced_qty' => 2.5, 'status' => 2, 'rate' => 0,
            ]);
            DB::table('mm_sales_orders')->where('id', $orderId)->update([
                'customer_id' => $customerId, 'site_id' => $siteId, 'mix_design_id' => $mixId,
            ]);
            $existing = DB::table('mm_batches')->where('plant_id', $plantId)->where('batch_no', 7254)->whereNull('deleted_at')->first();
            if ($existing) {
                if ((int) $existing->sales_order_id !== $orderId) throw new \RuntimeException('Batch 7254 already belongs to another order; no data changed.');
            }
            $batchId = $record('mm_batches', ['plant_id' => $plantId, 'batch_no' => 7254], [
                'sales_order_id' => $orderId, 'batch_size' => 2.5,
                'start_time' => '2026-10-29 09:51:23', 'end_time' => '2026-10-29 09:55:18',
                'status' => Batch::STATUS_COMPLETED, 'sync_status' => 'success', 'is_verified' => 1,
            ]);
            $dispatchId = $record('mm_dispatches', ['plant_id' => $plantId, 'batch_id' => $batchId], [
                'sales_order_id' => $orderId, 'customer_id' => $customerId,
                'truck_id' => $truckId, 'driver_id' => $driverId, 'delivered_qty' => 2.5,
            ]);
            DB::table('mm_dispatches')->where('id', $dispatchId)->update([
                'sales_order_id' => $orderId, 'customer_id' => $customerId, 'truck_id' => $truckId, 'driver_id' => $driverId,
            ]);
            // Source PDF: 20 material columns, per-m3 recipe, batch targets, three measured actual rows.
            $names = ['MSAND 1', 'MSAND 2', '20MM', '12MM', 'Agg 5', 'Agg 6', 'CEMENT 1', 'CEMENT 2', 'CEMENT 3', 'Cem 4', 'Cem 5', 'WATER', 'WC', 'Wtr 2', 'Ice', 'ADMIX 1', 'ADMIX 2', 'Adm 3', 'Adm 4', 'Sil'];
            $recipe = [410,500,610,440,0,0,340,0,0,0,0,160,0,0,0,1.8,0,0,0,0];
            $targets = [1067,1302,1519,1095,0,0,850,0,0,0,0,318,0,0,0,4.5,0,0,0,0];
            $runs = [
                [351,441,504,357,0,0,287,0,0,0,0,104,0,0,0,1.53,0,0,0,0],
                [359,435,506,364,0,0,286,0,0,0,0,105,0,0,0,1.50,0,0,0,0],
                [352,430,508,368,0,0,281,0,0,0,0,108,0,0,0,1.49,0,0,0,0],
            ];
            $mapping = [
                'MSAND 1' => ['MAT-SAND-M', 'MSAND 1'], 'MSAND 2' => ['FA-MSAND', 'MSAND 2'],
                '20MM' => ['AGG-20', '20MM'], '12MM' => ['AGG-10', '10MM'],
                'CEMENT 1' => ['CMT-01', 'CEM 1'], 'CEMENT 2' => ['CMT-001', 'CEM 2'],
                'CEMENT 3' => ['CEM-OPC53', 'CEM 3'], 'WATER' => ['WTR-01', 'WATER'],
                'WC' => ['WATER-RO-1', 'WC'], 'ADMIX 1' => ['ADM-01', 'ADM 1'],
                'ADMIX 2' => ['ADMIX-PCE-300', 'ADM 2'],
            ];
            $temporaryIds = DB::table('mm_products')->where('plant_id', $plantId)->where('code', 'like', 'GOKUL-PRINT-%')->pluck('id');
            DB::table('mm_mix_design_items')->where('mix_design_id', $mixId)->whereIn('product_id', $temporaryIds)->whereNull('deleted_at')->update(['deleted_at' => now()]);
            foreach ($names as $index => $name) {
                $productId = null;
                if (isset($mapping[$name])) {
                    [$code, $alias] = $mapping[$name];
                    $productId = DB::table('mm_products')->where('plant_id', $plantId)->where('code', $code)->whereNull('deleted_at')->value('id');
                    if (! $productId) throw new \RuntimeException("Existing product {$code} is required in plant {$plantId}.");
                    DB::table('mm_products')->where('id', $productId)->update(['alias' => $alias, 'updated_at' => now()]);
                } elseif ($targets[$index] != 0 || $recipe[$index] != 0) {
                    throw new \RuntimeException("No existing-product mapping for {$name}.");
                }
                // Empty source slots remain zero-valued columns without placeholder products.
                if ($productId) $record('mm_mix_design_items', ['mix_design_id' => $mixId, 'product_id' => $productId], [
                    'plant_id' => $plantId, 'uom_id' => $unitId, 'actual_quantity' => $recipe[$index], 'cross_quantity' => $recipe[$index],
                ]);
                foreach ($runs as $run => $actuals) {
                    // Only batch-level set totals are supplied. Store them once, without inventing per-run targets.
                    $target = $run === 0 ? $targets[$index] : 0;
                    $materialId = $record('mm_batch_materials', ['plant_id' => $plantId, 'batch_id' => $batchId, 'material_name' => $name.' - Run '.($run + 1)], [
                        'product_id' => $productId, 'uom_id' => $unitId, 'target_qty' => $target, 'actual_qty' => $actuals[$index],
                        'deviation_quantity' => $actuals[$index] - $target,
                    ]);
                    DB::table('mm_batch_materials')->where('id', $materialId)->update(['product_id' => $productId]);
                }
            }
            // Retire only this fixture's old products when no other active records use them.
            $references = DB::select("SELECT TABLE_NAME AS table_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'product_id'");
            foreach ($temporaryIds as $temporaryId) {
                $used = false;
                foreach ($references as $reference) {
                    $query = DB::table($reference->table_name)->where('product_id', $temporaryId);
                    if (\Illuminate\Support\Facades\Schema::hasColumn($reference->table_name, 'deleted_at')) $query->whereNull('deleted_at');
                    if ($query->exists()) { $used = true; break; }
                }
                if (! $used) DB::table('mm_products')->where('id', $temporaryId)->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now()]);
            }
            return $batchId;
        });

        session(['active_plant_id' => $plantId]);
        $batch = Batch::withoutPlantScope()->with(['plant', 'salesOrder.customer', 'salesOrder.site',
            'salesOrder.mixDesign.items.product', 'materials.product.category', 'dispatches.truck', 'dispatches.driver'])->findOrFail($batchId);
        session(['active_plant_id' => $batch->plant_id]);
        $report = JayadhityaBatchSheet::data($batch);
        if (count($report['runs']) !== 3 || abs($report['total_target'] - 6155.50) > .001 || abs($report['total_actual'] - 6150.52) > .001) {
            throw new \RuntimeException('Seeded batch does not match the source PDF totals/runs.');
        }
        foreach ([1 => 2045.53, 2 => 2056.50, 3 => 2048.49] as $run => $mass) {
            if (abs(array_sum($report['runs'][$run]) - $mass) > .001) throw new \RuntimeException("Run {$run} differs from the PDF.");
        }
        $html = view('pdfs.batches.batch_sheet', ['batch' => $batch, 'isPreview' => false])->render();
        $path = base_path("output/pdf/gokul-7254-plant-{$batch->plant_id}.pdf");
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        file_put_contents($path, app(BatchSheetBrowserPdf::class)->render($html));
        $this->command?->info("Plant {$batch->plant_id}, batch {$batch->id} (#7254): 3 runs, target 6155.50 kg, actual 6150.52 kg.");
        $this->command?->info(route('batches.report', $batch->id));
        $this->command?->info($path);
    }
}
