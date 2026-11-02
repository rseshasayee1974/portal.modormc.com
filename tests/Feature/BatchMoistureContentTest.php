<?php

/*
Author: Cipher
Created: 2026-11-02 11:27:52 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchMoistureContent;
use App\Models\User;
use App\Services\BatchMoistureService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BatchMoistureContentTest extends TestCase
{
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'moisture_test', 'database.connections.moisture_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array', 'session.driver' => 'array']);
        foreach (['mm_plants', 'mm_products', 'mm_batches', 'mm_batch_materials'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                if ($name !== 'mm_plants') {
                    $table->unsignedBigInteger('plant_id');
                }
                if ($name === 'mm_batch_materials') {
                    $table->unsignedBigInteger('batch_id');
                    $table->unsignedBigInteger('product_id');
                    $table->string('material_name');
                }
                foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
                    $table->unsignedBigInteger($column)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::create('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->id();
            foreach (['plant_id', 'transaction_type', 'action_type', 'reference_type', 'reference_id',
                'log_from', 'log_to', 'user_id', 'remarks', 'ip_address'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamp('created_at')->nullable();
        });
        (require database_path('migrations/2026_11_02_112614_create_batch_moisture_contents_table.php'))->up();
        DB::table('mm_plants')->insert([['id' => 1], ['id' => 2]]);
        DB::table('mm_products')->insert([['id' => 1, 'plant_id' => 1], ['id' => 2, 'plant_id' => 1], ['id' => 3, 'plant_id' => 2]]);
        DB::table('mm_batches')->insert([['id' => 1, 'plant_id' => 1], ['id' => 2, 'plant_id' => 1], ['id' => 3, 'plant_id' => 2]]);
        DB::table('mm_batch_materials')->insert([
            ['plant_id' => 1, 'batch_id' => 1, 'product_id' => 1, 'material_name' => 'Fine Sand - Run 1'],
            ['plant_id' => 1, 'batch_id' => 1, 'product_id' => 1, 'material_name' => 'Fine Sand - Run 2'],
        ]);
        session(['active_plant_id' => 1]);
        $this->actingAs(User::hydrate([['id' => 11]])->first());
        $this->batch = Batch::findOrFail(1);
    }

    private function readings(float $percentage = 2.125): array
    {
        return [['product_id' => 1, 'consumption_moisture_percentage' => $percentage,
            'measured_at' => '2026-11-02 09:00:00', 'notes' => 'Before loading']];
    }

    public function test_readings_persist_once_per_material_with_batch_details_and_creation_audit(): void
    {
        $service = app(BatchMoistureService::class);
        $service->sync($this->batch, $this->readings());
        $entry = $this->batch->moistureContents()->sole();
        $this->assertSame('2.125', $entry->consumption_moisture_percentage);
        $this->assertEquals(1, $entry->product_id);
        $this->assertSame('2026-11-02 09:00:00', $entry->measured_date_time->format('Y-m-d H:i:s'));
        $this->assertEquals(11, $entry->created_by);
        $this->assertEquals(11, $entry->updated_by);
        $service->sync($this->batch, $this->readings());
        $this->assertSame(1, BatchMoistureContent::count());
        $this->assertSame(0, DB::table('mm_inventory_audit_logs')->count());
        $this->assertSame(0, Batch::findOrFail(2)->moistureContents()->count());
    }

    public function test_updates_keep_creator_and_deletes_record_actor_and_allow_new_reading(): void
    {
        $service = app(BatchMoistureService::class);
        $service->sync($this->batch, $this->readings());
        $id = $this->batch->moistureContents()->sole()->id;
        $this->actingAs(User::hydrate([['id' => 12]])->first());
        $service->sync($this->batch, $this->readings(0));
        $entry = BatchMoistureContent::findOrFail($id);
        $this->assertSame('0.000', $entry->consumption_moisture_percentage);
        $this->assertEquals(11, $entry->created_by);
        $this->assertEquals(12, $entry->updated_by);
        $service->sync($this->batch, []);
        $this->assertSame(0, $this->batch->moistureContents()->count());
        $deleted = BatchMoistureContent::withTrashed()->findOrFail($id);
        $this->assertTrue($deleted->trashed());
        $this->assertEquals(12, $deleted->deleted_by);
        $this->assertSame(2, DB::table('mm_inventory_audit_logs')->count());
        $service->sync($this->batch, $this->readings(1));
        $this->assertSame(2, BatchMoistureContent::withTrashed()->count());
    }

    public function test_missing_measurement_time_is_recorded_and_batch_deletion_soft_deletes_readings(): void
    {
        app(BatchMoistureService::class)->sync($this->batch, [['product_id' => 1, 'consumption_moisture_percentage' => 100]]);
        $entry = $this->batch->moistureContents()->sole();
        $this->assertNotNull($entry->measured_date_time);
        $this->batch->delete();
        $this->assertTrue($entry->fresh()->trashed());
        $this->assertEquals(11, $entry->fresh()->deleted_by);
    }

    public function test_unrelated_material_is_rejected_without_changing_saved_readings(): void
    {
        $service = app(BatchMoistureService::class);
        $service->sync($this->batch, $this->readings());
        try {
            $service->sync($this->batch, [['product_id' => 2, 'consumption_moisture_percentage' => 3]]);
            $this->fail('Unrelated batch material was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('moisture_contents.0.product_id', $exception->errors());
        }
        $this->assertSame('2.125', $this->batch->moistureContents()->sole()->consumption_moisture_percentage);
    }

    public function test_design_and_consumption_are_stored_independently_and_support_zero_and_blank(): void
    {
        $service = app(BatchMoistureService::class);
        $readings = $this->readings();
        $readings[0]['design_moisture_percentage'] = 5;
        $this->assertFalse(Validator::make(['moisture_contents' => $readings], BatchMoistureService::rules(1))->fails());
        $service->sync($this->batch, $readings);
        $entry = $this->batch->moistureContents()->sole();
        $this->assertSame('5.000', $entry->design_moisture_percentage);
        $this->assertSame('2.125', $entry->consumption_moisture_percentage);
        $readings[0]['design_moisture_percentage'] = 0;
        $readings[0]['consumption_moisture_percentage'] = null;
        $this->assertFalse(Validator::make(['moisture_contents' => $readings], BatchMoistureService::rules(1))->fails());
        $service->sync($this->batch, $readings);
        $this->assertSame('0.000', $entry->fresh()->design_moisture_percentage);
        $this->assertSame('0.000', $entry->fresh()->consumption_moisture_percentage);
        $readings[0]['design_moisture_percentage'] = 101;
        $this->assertTrue(Validator::make(['moisture_contents' => $readings], BatchMoistureService::rules(1))->fails());
        $readings[0]['design_moisture_percentage'] = null;
        $this->assertTrue(Validator::make(['moisture_contents' => $readings], BatchMoistureService::rules(1))->fails());
    }

    public function test_update_endpoint_saves_only_moisture_without_other_batch_fields(): void
    {
        $request = \App\Http\Requests\UpdateBatchRequest::create('/batches/1', 'PUT', ['moisture_contents' => $this->readings()]);
        $request->setContainer($this->app);
        $validator = Validator::make($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
        $request->setValidator($validator);
        $controller = \Mockery::mock(\App\Http\Controllers\BatchController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->with('edit')->once();
        $response = $controller->update($request, $this->batch);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('2.125', $response->getData(true)['moisture_contents'][0]['consumption_moisture_percentage']);
        $this->assertSame(2, DB::table('mm_batch_materials')->count());
    }

    public function test_validation_rejects_invalid_percentage_duplicates_foreign_products_and_forged_audit(): void
    {
        $rules = BatchMoistureService::rules(1);
        foreach ([-1, 100.001, 2.1234, 'invalid', null] as $value) {
            $this->assertTrue(Validator::make(['moisture_contents' => [['product_id' => 1, 'consumption_moisture_percentage' => $value]]], $rules)->fails());
        }
        $this->assertTrue(Validator::make(['moisture_contents' => array_merge($this->readings(), $this->readings())], $rules)->fails());
        $this->assertTrue(Validator::make(['moisture_contents' => [['product_id' => 3, 'consumption_moisture_percentage' => 1]]], $rules)->fails());
        $this->assertTrue(Validator::make(['moisture_contents' => [['product_id' => 1, 'consumption_moisture_percentage' => 1, 'created_by' => 99]]], $rules)->fails());
        DB::table('mm_products')->where('id', 1)->update(['deleted_at' => now()]);
        $this->assertTrue(Validator::make(['moisture_contents' => $this->readings()], $rules)->fails());
        $this->assertFalse(Validator::make([], $rules)->fails());
        $this->assertFalse(Validator::make(['moisture_contents' => []], $rules)->fails());
    }
}
