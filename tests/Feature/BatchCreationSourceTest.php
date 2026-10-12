<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 16:02:14 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Requests\StoreBatchRequest;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchCreationSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'batch_source_test', 'database.connections.batch_source_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        session(['active_plant_id' => 1]);
        DB::statement('CREATE TABLE mm_custom_settings (id INTEGER PRIMARY KEY, plant_id INTEGER, module_name TEXT, settings TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_sales_orders (id INTEGER PRIMARY KEY, plant_id INTEGER, total_qty REAL, produced_qty REAL, status INTEGER, rate REAL DEFAULT 100, is_tax_inclusive INTEGER DEFAULT 0, deleted_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE mm_concrete_batching_schedules (id INTEGER PRIMARY KEY, plant_id INTEGER, sales_order_id INTEGER, qty_m3 REAL, status TEXT, batch_id INTEGER, dispatch_id INTEGER, vehicle_id INTEGER, driver_id INTEGER, site_id INTEGER, deleted_at TEXT, updated_at TEXT)');
        DB::table('mm_sales_orders')->insert([
            ['id' => 1, 'plant_id' => 1, 'total_qty' => 40, 'produced_qty' => 10],
            ['id' => 2, 'plant_id' => 2, 'total_qty' => 40, 'produced_qty' => 0],
        ]);
        DB::table('mm_concrete_batching_schedules')->insert([
            ['id' => 1, 'plant_id' => 1, 'sales_order_id' => 1, 'qty_m3' => 6, 'status' => 'scheduled'],
            ['id' => 2, 'plant_id' => 2, 'sales_order_id' => 2, 'qty_m3' => 6, 'status' => 'scheduled'],
        ]);
    }

    private function mode(string $source): void
    {
        DB::table('mm_custom_settings')->updateOrInsert(['plant_id' => 1, 'module_name' => 'batching'], [
            'settings' => json_encode(['batch_creation_source' => $source]),
        ]);
    }

    private function validateBatch(array $data = []): array
    {
        $request = StoreBatchRequest::create('/batches', 'POST', array_replace([
            'sales_order_id' => 1, 'batch_size' => 6, 'empty_weight_truck' => 10,
        ], $data));
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => null);
        $request->validateResolved();
        return $request->validated();
    }

    private function assertInvalid(array $data, string $field): void
    {
        try {
            $this->validateBatch($data);
            $this->fail('Expected batch validation to fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    public function test_default_sales_order_and_both_creation_sources(): void
    {
        $this->assertSame(1, $this->validateBatch()['sales_order_id']);
        $this->assertInvalid(['batching_schedule_id' => 1], 'batching_schedule_id');
        $this->mode('both');
        $this->assertSame(1, $this->validateBatch()['sales_order_id']);
        $data = $this->validateBatch(['batching_schedule_id' => 1, 'sales_order_id' => 2]);
        $this->assertSame(1, $data['sales_order_id']);
        $this->assertSame(1, $data['batching_schedule_id']);
    }

    public function test_schedule_required_and_quantity_and_plant_scope_enforced(): void
    {
        $this->mode('batch_schedule');
        $this->assertInvalid([], 'batching_schedule_id');
        $this->assertInvalid(['batching_schedule_id' => 2], 'batching_schedule_id');
        $this->assertSame(7, $this->validateBatch(['batching_schedule_id' => 1, 'batch_size' => 7])['batch_size']);
        $this->assertInvalid(['batching_schedule_id' => 1, 'batch_size' => 31], 'batch_size');
        $this->assertSame(1, $this->validateBatch(['batching_schedule_id' => 1, 'sales_order_id' => null])['sales_order_id']);
        $this->mode('both');
        $this->assertInvalid(['sales_order_id' => 2], 'sales_order_id');
    }

    public function test_used_cancelled_and_deleted_schedules_cannot_create_batches(): void
    {
        $this->mode('both');
        foreach (['batch_id' => 10, 'dispatch_id' => 20, 'status' => 'cancelled', 'deleted_at' => '2026-10-09'] as $field => $value) {
            DB::table('mm_concrete_batching_schedules')->where('id', 1)->update([$field => $value]);
            $this->assertInvalid(['batching_schedule_id' => 1], 'batching_schedule_id');
            DB::table('mm_concrete_batching_schedules')->where('id', 1)->update([$field => $field === 'status' ? 'scheduled' : null]);
        }
    }

    public function test_store_links_schedule_sales_order_to_batch_and_draft_dispatch(): void
    {
        $this->mode('both');
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, plant_id INTEGER, sales_order_id INTEGER, batch_no INTEGER, batch_size REAL, status INTEGER, shift TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE mm_batch_materials (id INTEGER PRIMARY KEY, plant_id INTEGER, batch_id INTEGER, deleted_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE mm_machines (id INTEGER PRIMARY KEY)');
        DB::table('mm_machines')->insert([['id' => 10], ['id' => 11]]);
        DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY AUTOINCREMENT, sales_order_id INTEGER, batch_id INTEGER, plant_id INTEGER, customer_id INTEGER, mixdesign_id INTEGER, unload_site_id INTEGER, load_site_id INTEGER, uom_id INTEGER, truck_id INTEGER, transport_id INTEGER, driver_id INTEGER, operator_id INTEGER, sales_executive_id INTEGER, concrete_pump TEXT, empty_weight_truck REAL, loaded_weight_truck REAL, net_weight REAL, empty_time TEXT, load_time TEXT, dispatch_status TEXT, prefix TEXT, dispatch_no TEXT, dispatch_time TEXT, delivered_qty REAL, load_rate REAL, load_tax_id INTEGER, load_untax_amount REAL, load_tax_amount REAL, load_total_amount REAL, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_dispatch_statuses (id INTEGER PRIMARY KEY AUTOINCREMENT, dispatch_id INTEGER, plant_id INTEGER, is_tax_inclusive INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::table('mm_concrete_batching_schedules')->where('id', 1)->update(['vehicle_id' => 10]);
        $controller = new class extends \App\Http\Controllers\BatchController {
            protected function authorizeModule(string $action, ?string $module = null): void {}
        };
        \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($controller) {
            foreach ([[], ['batching_schedule_id' => 1, 'sales_order_id' => 2]] as $source) {
                $request = StoreBatchRequest::create('/batches', 'POST', array_replace([
                    'sales_order_id' => 1, 'batch_size' => 6, 'empty_weight_truck' => 10, 'shift' => 'Day', 'truck_id' => 11,
                ], $source));
                $request->setContainer(app())->setRedirector(app('redirect'));
                $request->setUserResolver(fn () => null);
                $request->validateResolved();
                $controller->store($request);
            }
        });
        $schedule = DB::table('mm_concrete_batching_schedules')->find(1);
        $batch = DB::table('mm_batches')->find($schedule->batch_id);
        $dispatch = DB::table('mm_dispatches')->find($schedule->dispatch_id);
        $this->assertSame(2, DB::table('mm_batches')->count());
        $this->assertSame(1, $batch->sales_order_id);
        $this->assertSame(1, $dispatch->sales_order_id);
        $this->assertSame($batch->id, $dispatch->batch_id);
        $this->assertSame(11, $dispatch->truck_id);
        $this->assertSame('Draft', $dispatch->dispatch_status);
        $this->assertSame(\App\Models\Batch::STATUS_PLANNED, $batch->status);
        $this->assertInvalid(['batching_schedule_id' => 1], 'batching_schedule_id');
    }

    public function test_resource_reservations_ignore_deleted_batches_and_keep_standalone_drafts(): void
    {
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, plant_id INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY, plant_id INTEGER, batch_id INTEGER, dispatch_status TEXT, deleted_at TEXT)');
        DB::table('mm_batches')->insert([
            ['id' => 1, 'plant_id' => 1, 'deleted_at' => null],
            ['id' => 2, 'plant_id' => 1, 'deleted_at' => '2026-10-09'],
        ]);
        DB::table('mm_dispatches')->insert([
            ['id' => 1, 'plant_id' => 1, 'batch_id' => null, 'dispatch_status' => 'Draft', 'deleted_at' => null],
            ['id' => 2, 'plant_id' => 1, 'batch_id' => 1, 'dispatch_status' => 'Draft', 'deleted_at' => null],
            ['id' => 3, 'plant_id' => 1, 'batch_id' => 2, 'dispatch_status' => 'Draft', 'deleted_at' => null],
            ['id' => 4, 'plant_id' => 1, 'batch_id' => 1, 'dispatch_status' => 'Draft', 'deleted_at' => '2026-10-09'],
        ]);
        DB::table('mm_concrete_batching_schedules')->where('id', 1)->update(['batch_id' => 2]);
        $this->assertSame([1, 2], \App\Models\Dispatch::withActiveBatch()->orderBy('id')->pluck('id')->all());
        $this->assertSame([], \App\Models\ConcreteBatchingSchedule::withActiveBatch()->pluck('id')->all());
        DB::table('mm_concrete_batching_schedules')->where('id', 1)->update(['batch_id' => null]);
        $this->assertSame([1], \App\Models\ConcreteBatchingSchedule::withActiveBatch()->pluck('id')->all());
    }

    public function test_deleted_batch_numbers_can_be_reused_but_active_duplicates_and_restoration_are_rejected(): void
    {
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, plant_id INTEGER, batch_no INTEGER, deleted_at TEXT)');
        DB::statement('CREATE UNIQUE INDEX mm_batches_plant_batch_no_unique ON mm_batches (plant_id, batch_no)');
        $deleted = DB::table('mm_batches')->insertGetId(['plant_id' => 1, 'batch_no' => 25, 'deleted_at' => '2026-10-09']);
        $migration = require database_path('migrations/2026_10_09_164717_allow_reuse_of_deleted_batch_numbers.php');
        $migration->up();
        $migration->up();
        DB::table('mm_batches')->insert(['plant_id' => 1, 'batch_no' => 25]);
        DB::table('mm_batches')->insert(['plant_id' => 2, 'batch_no' => 25]);
        $this->assertSame(3, DB::table('mm_batches')->count());
        foreach (['insert', 'restore'] as $operation) {
            try {
                if ($operation === 'insert') {
                    DB::table('mm_batches')->insert(['plant_id' => 1, 'batch_no' => 25]);
                } else {
                    DB::table('mm_batches')->where('id', $deleted)->update(['deleted_at' => null]);
                }
                $this->fail('Active duplicate batch numbers must be rejected.');
            } catch (\Illuminate\Database\QueryException $error) {
                $this->assertStringContainsString('UNIQUE', $error->getMessage());
            }
        }
    }

    public function test_linking_and_batch_status_updates_keep_schedule_in_sync(): void
    {
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, plant_id INTEGER, status INTEGER, deleted_at TEXT, updated_at TEXT)');
        DB::table('mm_batches')->insert(['id' => 1, 'plant_id' => 1, 'status' => 2]);
        $schedule = \App\Models\ConcreteBatchingSchedule::findOrFail(1);
        $schedule->update(['batch_id' => 1]);
        $this->assertSame('batching', $schedule->fresh()->status);
        $schedule->updateQuietly(['dispatch_id' => 10]);
        $dispatch = new \App\Models\Dispatch(['plant_id' => 1, 'batch_id' => 1, 'dispatch_status' => 'Draft']);
        $dispatch->id = 10;
        $dispatch->syncWithBatchingSchedule();
        $this->assertSame('batching', $schedule->fresh()->status);
        $batch = \App\Models\Batch::findOrFail(1);
        foreach ([3 => 'in_transit', 4 => 'completed', 5 => 'cancelled'] as $status => $expected) {
            $batch->update(['status' => $status]);
            $this->assertSame($expected, $schedule->fresh()->status);
        }
        $batch->update(['status' => 3]);
        $schedule->updateQuietly(['status' => 'pouring']);
        $batch->syncBatchingSchedules();
        $this->assertSame('pouring', $schedule->fresh()->status);
    }
}
