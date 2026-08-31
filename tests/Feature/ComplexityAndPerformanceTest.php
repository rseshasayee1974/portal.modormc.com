<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\WorkOrder;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Personnel;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComplexityAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = storage_path('logs/testing_data.log');
        $this->plant = Plant::factory()->create();
        $this->user = User::factory()->create(['default_plant_id' => $this->plant->id]);
        $this->actingAs($this->user);

        // Session current_plant_id
        session(['current_plant_id' => $this->plant->id]);
    }

    /**
     * Helper to log execution details and print to console.
     */
    protected function logPerformance(string $action, int $queryCount, float $elapsedMs): void
    {
        $logMessage = sprintf(
            "[%s] [PERFORMANCE] %s | Query Count: %d | Time: %.2f ms\n",
            now()->toDateTimeString(),
            $action,
            $queryCount,
            $elapsedMs
        );
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Output to terminal output
        fwrite(STDERR, sprintf("\n    ↳  [Performance] %s: %.2f ms (Queries: %d)\n", $action, $elapsedMs, $queryCount));
    }

    /**
     * Helper to create batch dependencies.
     */
    protected function createBatchRecord(int $batchNo = 1): Batch
    {
        $workOrder = WorkOrder::factory()->create(['plant_id' => $this->plant->id]);
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $driver = Personnel::factory()->create(['plant_id' => $this->plant->id]);
        $operator = Personnel::factory()->create(['plant_id' => $this->plant->id]);

        return Batch::create([
            'plant_id' => $this->plant->id,
            'sales_order_id' => $workOrder->id,
            'batch_no' => $batchNo,
            'batch_size' => 1.5,
            'status' => Batch::STATUS_PLANNED,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'operator_id' => $operator->id,
            'start_time' => now(),
            'end_time' => now()->addHour(),
        ]);
    }

    /**
     * Helper to create dispatch dependencies.
     */
    protected function createDispatchRecord(Batch $batch): Dispatch
    {
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $driver = Personnel::factory()->create(['plant_id' => $this->plant->id]);

        return Dispatch::create([
            'sales_order_id' => $batch->sales_order_id,
            'batch_id' => $batch->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'dispatch_time' => now(),
            'delivered_qty' => $batch->batch_size,
        ]);
    }

    /**
     * Test time complexity of BatchController@index.
     */
    public function test_batch_index_has_constant_query_complexity()
    {
        // 1. Create 1 Batch
        $this->createBatchRecord(1);

        DB::enableQueryLog();
        $start = microtime(true);
        $response1 = $this->get(route('batches.index'));
        $elapsed1 = (microtime(true) - $start) * 1000;
        $response1->assertStatus(200);
        $queriesForOneRecord = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->logPerformance('batches.index (1 record)', $queriesForOneRecord, $elapsed1);

        // 2. Create 9 more Batches (10 total)
        for ($i = 2; $i <= 100000; $i++) {
            $this->createBatchRecord($i);
        }

        DB::enableQueryLog();
        $start = microtime(true);
        $response2 = $this->get(route('batches.index'));
        $elapsed2 = (microtime(true) - $start) * 1000;
        $response2->assertStatus(200);
        $queriesForTenRecords = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->logPerformance('batches.index (10 records)', $queriesForTenRecords, $elapsed2);

        $this->assertLessThanOrEqual(
            $queriesForOneRecord + 2,
            $queriesForTenRecords,
            "BatchController@index has linear query complexity O(N) instead of O(1) (N+1 queries detected!)."
        );
    }

    /**
     * Test that BatchController@index only returns the optimized list fields.
     */
    public function test_batch_index_does_not_load_unnecessary_relations()
    {
        $batch = $this->createBatchRecord(1);
        $this->createDispatchRecord($batch);

        $response = $this->get(route('batches.index'));
        $response->assertStatus(200);

        $response->assertInertia(function ($page) {
            $batches = $page->toArray()['props']['batches']['data'] ?? $page->toArray()['props']['batches'] ?? [];
            foreach ($batches as $row) {
                $this->assertArrayNotHasKey('materials', $row, "Materials loaded eagerly in listing");
                $this->assertArrayNotHasKey('photos', $row, "Photos loaded eagerly in listing");
            }
        });
    }

    /**
     * Test time complexity of BatchController@show.
     */
    public function test_batch_show_loads_required_relations_for_details()
    {
        $batch = $this->createBatchRecord(1);
        $this->createDispatchRecord($batch);

        DB::enableQueryLog();
        $start = microtime(true);
        $response = $this->get(route('batches.show', $batch->id));
        $elapsed = (microtime(true) - $start) * 1000;
        $response->assertStatus(200);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->logPerformance('batches.show', $queryCount, $elapsed);

        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('materials', $data);
        $this->assertArrayHasKey('dispatches', $data);
        $this->assertArrayHasKey('work_order', $data);
    }

    /**
     * Test time complexity of DispatchController@index.
     */
    public function test_dispatch_index_has_constant_query_complexity()
    {
        $batch1 = $this->createBatchRecord(1);
        $this->createDispatchRecord($batch1);

        DB::enableQueryLog();
        $start = microtime(true);
        $response1 = $this->get(route('dispatches.index'));
        $elapsed1 = (microtime(true) - $start) * 1000;
        $response1->assertStatus(200);
        $queriesForOneRecord = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->logPerformance('dispatches.index (1 record)', $queriesForOneRecord, $elapsed1);

        // Create 9 more Dispatches
        for ($i = 2; $i <= 10; $i++) {
            $batch = $this->createBatchRecord($i);
            $this->createDispatchRecord($batch);
        }

        DB::enableQueryLog();
        $start = microtime(true);
        $response2 = $this->get(route('dispatches.index'));
        $elapsed2 = (microtime(true) - $start) * 1000;
        $response2->assertStatus(200);
        $queriesForTenRecords = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->logPerformance('dispatches.index (10 records)', $queriesForTenRecords, $elapsed2);

        $this->assertLessThanOrEqual(
            $queriesForOneRecord + 2,
            $queriesForTenRecords,
            "DispatchController@index has linear query complexity O(N) instead of O(1) (N+1 queries detected!)."
        );
    }

    /**
     * Test that DispatchController@index only returns the optimized list fields.
     */
    public function test_dispatch_index_does_not_load_unnecessary_relations()
    {
        $batch = $this->createBatchRecord(1);
        $this->createDispatchRecord($batch);

        $response = $this->get(route('dispatches.index'));
        $response->assertStatus(200);

        $response->assertInertia(function ($page) {
            $dispatches = $page->toArray()['props']['dispatches']['data'] ?? $page->toArray()['props']['dispatches'] ?? [];
            foreach ($dispatches as $row) {
                $this->assertArrayNotHasKey('payments', $row, "Payments loaded eagerly in listing");
                $this->assertArrayNotHasKey('tax', $row, "Tax loaded eagerly in listing");
            }
        });
    }

    /**
     * Test time complexity of DispatchController@show.
     */
    public function test_dispatch_show_loads_required_relations_for_details()
    {
        $batch = $this->createBatchRecord(1);
        $dispatch = $this->createDispatchRecord($batch);

        DB::enableQueryLog();
        $start = microtime(true);
        $response = $this->get(route('dispatches.show', $dispatch->id));
        $elapsed = (microtime(true) - $start) * 1000;
        $response->assertStatus(200);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->logPerformance('dispatches.show', $queryCount, $elapsed);

        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('payments', $data);
        $this->assertArrayHasKey('work_order', $data);
    }
}
