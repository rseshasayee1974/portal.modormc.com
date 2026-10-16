<?php
/*
Author: Cipher
Created: 2026-10-16 10:51:13 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\QC\QcSample;
use App\Models\QC\QcTestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QcDueDispatchDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'QC demo data is restricted to the local environment.');

        Model::withoutEvents(function () {
            DB::transaction(function () {
                $source = SalesOrder::with(['mixDesign.concreteGrade', 'site'])
                    ->where('plant_id', 1)->whereNotNull('customer_id')
                    ->whereHas('mixDesign.concreteGrade')->latest('id')->firstOrFail();
                $userId = User::query()->value('id');
                $today = now('Asia/Kolkata')->startOfDay();
                $tag = 'QC-DEMO-'.$today->format('Ymd');
                $type = QcTestType::where('plant_id', 1)->where('code', 'QC-DEMO-COMP')->first();
                if (!$type) {
                    $type = QcTestType::where('plant_id', 1)->where('is_active', true)
                        ->where('name', 'like', '%Compressive%')->firstOrFail()->replicate();
                    $type->forceFill(['name' => 'QC Demo Cube Compressive Strength', 'code' => 'QC-DEMO-COMP', 'is_active' => true]);
                    $type->save();
                }
                $order = SalesOrder::firstOrCreate(
                    ['plant_id' => 1, 'prefix' => 'QC-DEMO/', 'order_no' => $today->format('Ymd')],
                    ['customer_id' => $source->customer_id, 'site_id' => $source->site_id,
                        'mix_design_id' => $source->mix_design_id, 'total_qty' => 3,
                        'produced_qty' => 3, 'rate' => 0, 'status' => SalesOrder::STATUS_COMPLETED,
                        'created_by' => $userId]
                );
                foreach ([7, 15, 28] as $age) {
                    $reference = $tag.'-'.$age.'D';
                    if (Dispatch::where('plant_id', 1)->where('dispatch_no', $reference)->exists()) {
                        $this->command?->line($reference.' already exists; skipped.');
                        continue;
                    }
                    $date = $today->copy()->subDays($age)->setTime(10, 0);
                    $batch = Batch::create([
                        'plant_id' => 1, 'sales_order_id' => $order->id,
                        'batch_no' => ((int) Batch::withTrashed()->max('batch_no')) + 1,
                        'batch_size' => 1, 'status' => Batch::STATUS_COMPLETED,
                        'start_time' => $date, 'end_time' => $date, 'created_by' => $userId,
                    ]);
                    $dispatch = Dispatch::create([
                        'plant_id' => 1, 'sales_order_id' => $order->id, 'batch_id' => $batch->id,
                        'customer_id' => $source->customer_id, 'unload_site_id' => $source->site_id,
                        'mixdesign_id' => $source->mix_design_id, 'dispatch_no' => $reference,
                        'dispatch_reference' => 'Dummy QC data - no invoice',
                        'dispatch_time' => $date, 'delivery_time' => $date->copy()->addMinutes(30),
                        'dispatch_status' => 'Dispatched', 'status' => 1, 'delivered_qty' => 1,
                        'created_by' => $userId,
                    ]);
                    $sample = QcSample::create([
                        'plant_id' => 1, 'sample_no' => 'SMP-'.$reference,
                        'sample_date' => $date, 'dispatch_id' => $dispatch->id, 'batch_id' => $batch->id,
                        'customer_id' => $source->customer_id,
                        'concrete_grade_id' => $source->mixDesign->concrete_grade_id,
                        'site_name' => $source->site?->name, 'source_location' => 'QC demo sampling',
                        'specimen_size' => '150x150x150 mm', 'specimen_count' => 3,
                        'sample_quantity' => '3 Cubes', 'slump_mm' => 120, 'concrete_temp_c' => 28,
                        'curing_tank_id' => 'Demo tank', 'status' => 'pending_test',
                        'remarks' => 'Dummy data for testing the '.$age.'D dispatch due list.',
                        'sampled_by' => $userId, 'created_by' => $userId,
                    ]);
                    preg_match('/(\d+)/', $source->mixDesign->concreteGrade->name, $matches);
                    $strength = (float) ($matches[1] ?? 25);
                    $test = $sample->tests()->create([
                        'plant_id' => 1, 'test_type_id' => $type->id, 'test_no' => 'TST-'.$reference,
                        'age_days' => $age, 'scheduled_date' => $today->toDateString(),
                        'target_strength' => round($strength * ($age === 7 ? .7 : ($age === 15 ? .9 : 1)), 2),
                        'min_strength' => round($strength * ($age === 7 ? .65 : ($age === 15 ? .85 : .95)), 2),
                        'unit' => 'MPa', 'overall_status' => 'pending', 'approval_status' => 'draft',
                        'created_by' => $userId,
                    ]);
                    $test->ensureSetsAndSpecimensInitialized();
                    $this->command?->line($reference.' | dispatched '.$date->toDateString().' | test '.$test->id.' | due '.$today->toDateString());
                }
            });
        });
    }
}
