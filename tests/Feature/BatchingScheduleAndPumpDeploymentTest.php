<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\ConcreteBatchingSchedule;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\MixDesign;
use App\Models\Personnel;
use App\Models\Plant;
use App\Models\PumpBoomDeploymentSchedule;
use App\Models\SalesOrder;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchingScheduleAndPumpDeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Site $site;
    protected MixDesign $mixDesign;
    protected Machine $truck;
    protected Machine $pump;
    protected Personnel $driver;
    protected Personnel $operator;
    protected SalesOrder $salesOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plant = Plant::factory()->create();
        $this->user = User::factory()->create([
            'default_plant_id' => $this->plant->id,
        ]);

        session(['active_plant_id' => $this->plant->id]);
        $this->actingAs($this->user);

        $this->site = Site::factory()->create(['plant_id' => $this->plant->id]);
        $this->mixDesign = MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $this->truck = Machine::factory()->create([
            'plant_id' => $this->plant->id,
            'registration' => 'TM-01-AB-1234',
            'vehicle_type' => 'transit_mixer',
            'capacity' => 6.0,
        ]);

        $this->pump = Machine::factory()->create([
            'plant_id' => $this->plant->id,
            'registration' => 'PUMP-36M-01',
            'vehicle_type' => 'boom_pump',
            'capacity' => 36.0,
        ]);

        $this->driver = Personnel::factory()->create([
            'plant_id' => $this->plant->id,
            'first_name' => 'John',
            'last_name' => 'Driver',
        ]);

        $this->operator = Personnel::factory()->create([
            'plant_id' => $this->plant->id,
            'first_name' => 'Sam',
            'last_name' => 'Operator',
        ]);

        $this->salesOrder = SalesOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->site->customer_id ?? null,
            'rate' => 4500,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /*               CONCRETE BATCHING SCHEDULE CONTROLLER TESTS                 */
    /* -------------------------------------------------------------------------- */

    public function test_concrete_batching_schedule_index_and_dropdowns()
    {
        $response = $this->get(route('production.batching-schedules.index'));
        $response->assertStatus(200);

        $dropdownResponse = $this->getJson(route('production.batching-schedules.dropdowns'));
        $dropdownResponse->assertStatus(200)
            ->assertJsonStructure(['sites', 'mixDesigns', 'vehicles', 'drivers', 'salesOrders', 'pumpTypes']);
    }

    public function test_concrete_batching_schedule_store_creates_batch_and_dispatch()
    {
        $payload = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Pier Foundation Pour A',
            'site_id'         => $this->site->id,
            'mix_design_id'   => $this->mixDesign->id,
            'qty_m3'          => 6.0,
            'order_volume_m3' => 30.0,
            'vehicle_id'      => $this->truck->id,
            'driver_id'       => $this->driver->id,
            'pump_type'       => 'Boom_pump', // tests case insensitivity
            'pump_vehicle_id' => $this->pump->id,
            'sales_order_id'  => $this->salesOrder->id,
            'batching_time'   => '2026-09-08 10:00:00',
            'dispatch_time'   => '2026-09-08 10:15:00',
            'eta_site'        => '2026-09-08 11:00:00',
            'status'          => 'batching',
            'notes'           => 'High slump required',
        ];

        $response = $this->postJson(route('production.batching-schedules.store'), $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $scheduleId = $response->json('schedule.id');
        $this->assertDatabaseHas('mm_concrete_batching_schedules', [
            'id'             => $scheduleId,
            'pour_reference' => 'Pier Foundation Pour A',
            'qty_m3'         => 6.0,
            'status'         => 'batching',
            'pump_type'      => 'boom_pump',
        ]);

        $batchId = $response->json('schedule.batch_id');
        $this->assertNotNull($batchId);
        $this->assertDatabaseHas('mm_batches', [
            'id'         => $batchId,
            'batch_size' => 6.0,
            'status'     => Batch::STATUS_LOADING,
        ]);

        $dispatchId = $response->json('schedule.dispatch_id');
        $this->assertNotNull($dispatchId);
        $this->assertDatabaseHas('mm_dispatches', [
            'id'              => $dispatchId,
            'delivered_qty'   => 6.0,
            'dispatch_status' => 'Loading',
        ]);
    }

    public function test_concrete_batching_schedule_update_syncs_batch_and_dispatch()
    {
        $schedule = ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Slab Pour B',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 5.0,
            'order_volume_m3'     => 20.0,
            'remaining_volume_m3' => 15.0,
            'vehicle_id'          => $this->truck->id,
            'driver_id'           => $this->driver->id,
            'pump_type'           => 'line_pump',
            'status'              => 'scheduled',
        ]);

        $batch = Batch::create([
            'plant_id'   => $this->plant->id,
            'batch_no'   => 101,
            'batch_size' => 5.0,
            'status'     => Batch::STATUS_PLANNED,
        ]);
        $schedule->update(['batch_id' => $batch->id]);

        $updatePayload = [
            'schedule_date'    => '2026-09-08',
            'pour_reference'   => 'Slab Pour B',
            'site_id'          => $this->site->id,
            'mix_design_id'    => $this->mixDesign->id,
            'qty_m3'           => 6.5,
            'order_volume_m3'  => 20.0,
            'vehicle_id'       => $this->truck->id,
            'driver_id'        => $this->driver->id,
            'pump_type'        => 'line_pump',
            'batching_time'    => '2026-09-08 14:00:00',
            'dispatch_time'    => '2026-09-08 14:20:00',
            'eta_site'         => '2026-09-08 15:00:00',
            'unloading_start'  => '2026-09-08 15:10:00',
            'unloading_end'    => '2026-09-08 15:50:00',
            'status'           => 'completed',
        ];

        $response = $this->putJson(route('production.batching-schedules.update', $schedule->id), $updatePayload);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('mm_concrete_batching_schedules', [
            'id'     => $schedule->id,
            'qty_m3' => 6.5,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('mm_batches', [
            'id'         => $batch->id,
            'batch_size' => 6.5,
            'status'     => Batch::STATUS_COMPLETED,
        ]);
    }

    public function test_concrete_batching_schedule_update_status_fast_transition()
    {
        $schedule = ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Column Pour C',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 12.0,
            'remaining_volume_m3' => 6.0,
            'pump_type'           => 'direct_pour',
            'status'              => 'scheduled',
        ]);

        $response = $this->patchJson(route('production.batching-schedules.update-status', $schedule->id), [
            'status' => 'in_transit',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $fresh = $schedule->fresh();
        $this->assertEquals('in_transit', $fresh->status);
        $this->assertNotNull($fresh->dispatch_time);
        $this->assertNotNull($fresh->eta_site);
    }

    public function test_concrete_batching_schedule_create_dispatch_ticket()
    {
        $schedule = ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Foundation Pour D',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 18.0,
            'remaining_volume_m3' => 12.0,
            'vehicle_id'          => $this->truck->id,
            'driver_id'           => $this->driver->id,
            'pump_type'           => 'boom_pump',
            'sales_order_id'      => $this->salesOrder->id,
            'status'              => 'batching',
        ]);

        $response = $this->postJson(route('production.batching-schedules.create-dispatch', $schedule->id));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $fresh = $schedule->fresh();
        $this->assertNotNull($fresh->dispatch_id);
        $this->assertEquals('in_transit', $fresh->status);

        $this->assertDatabaseHas('mm_dispatches', [
            'id'              => $fresh->dispatch_id,
            'delivered_qty'   => 6.0,
            'dispatch_status' => 'In Transit',
        ]);
    }

    public function test_concrete_batching_schedule_validates_transit_mixer_overlap()
    {
        ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Pour 1',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 12.0,
            'remaining_volume_m3' => 6.0,
            'vehicle_id'          => $this->truck->id,
            'pump_type'           => 'direct_pour',
            'batching_time'       => '2026-09-08 09:00:00',
            'eta_site'            => '2026-09-08 09:40:00',
            'status'              => 'in_transit',
        ]);

        // Second booking for same truck in overlapping window (09:30)
        $conflictingPayload = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Pour 2',
            'site_id'         => $this->site->id,
            'mix_design_id'   => $this->mixDesign->id,
            'qty_m3'          => 6.0,
            'order_volume_m3' => 12.0,
            'vehicle_id'      => $this->truck->id,
            'pump_type'       => 'direct_pour',
            'batching_time'   => '2026-09-08 09:30:00',
            'status'          => 'scheduled',
        ];

        $response = $this->postJson(route('production.batching-schedules.store'), $conflictingPayload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_concrete_batching_schedule_destroy_cleans_up_and_recalculates()
    {
        $batch = Batch::create([
            'plant_id'   => $this->plant->id,
            'batch_no'   => 202,
            'batch_size' => 6.0,
            'status'     => Batch::STATUS_PLANNED,
        ]);

        $dispatch = Dispatch::create([
            'plant_id'          => $this->plant->id,
            'batch_id'          => $batch->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'delivered_qty'     => 6.0,
            'dispatch_status'   => 'Draft',
            'payment_mode'      => 'credit',
            'load_rate'         => 4500,
            'load_untax_amount' => 27000,
            'load_total_amount' => 27000,
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '1',
        ]);

        $schedule = ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Pour To Delete',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 12.0,
            'remaining_volume_m3' => 6.0,
            'pump_type'           => 'line_pump',
            'batch_id'            => $batch->id,
            'dispatch_id'         => $dispatch->id,
            'status'              => 'scheduled',
        ]);

        $response = $this->deleteJson(route('production.batching-schedules.destroy', $schedule->id));
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSoftDeleted('mm_concrete_batching_schedules', ['id' => $schedule->id]);
    }

    /* -------------------------------------------------------------------------- */
    /*               PUMP & BOOM DEPLOYMENT CONTROLLER TESTS                      */
    /* -------------------------------------------------------------------------- */

    public function test_pump_boom_deployment_index_and_dropdowns()
    {
        $response = $this->get(route('production.pump-deployments.index'));
        $response->assertStatus(200);

        $dropdownResponse = $this->getJson(route('production.pump-deployments.dropdowns'));
        $dropdownResponse->assertStatus(200)
            ->assertJsonStructure(['sites', 'mixDesigns', 'machines', 'operators', 'pumpTypes']);
    }

    public function test_pump_boom_deployment_store_validates_boom_length()
    {
        // Boom pump without boom_length_m must fail
        $payloadWithoutLength = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Commercial Slab Pour',
            'site_id'         => $this->site->id,
            'pour_location'   => 'Tower 1 Basement B2',
            'mix_design_id'   => $this->mixDesign->id,
            'planned_qty_m3'  => 50.0,
            'pump_type'       => 'boom_pump',
            'pump_vehicle_id' => $this->pump->id,
        ];

        $response = $this->postJson(route('production.pump-deployments.store'), $payloadWithoutLength);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['boom_length_m']);

        // With boom length, should succeed
        $payloadWithLength = array_merge($payloadWithoutLength, [
            'boom_length_m'     => 36,
            'pump_arrival_time' => '2026-09-08 07:00:00',
            'setup_start_time'  => '2026-09-08 07:15:00',
            'setup_end_time'    => '2026-09-08 07:45:00',
            'pour_start_time'   => '2026-09-08 08:00:00',
            'planned_end_time'  => '2026-09-08 12:00:00',
            'operator_id'       => $this->operator->id,
        ]);

        $successResponse = $this->postJson(route('production.pump-deployments.store'), $payloadWithLength);
        $successResponse->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('mm_pump_boom_deployment_schedule', [
            'pour_reference' => 'Commercial Slab Pour',
            'boom_length_m'  => 36,
            'pump_type'      => 'boom_pump',
            'status'         => 'scheduled',
        ]);
    }

    public function test_pump_boom_deployment_update_and_status_advance()
    {
        $deployment = PumpBoomDeploymentSchedule::create([
            'plant_id'        => $this->plant->id,
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Bridge Deck Pour',
            'site_id'         => $this->site->id,
            'pour_location'   => 'Span 3',
            'mix_design_id'   => $this->mixDesign->id,
            'planned_qty_m3'  => 40.0,
            'pump_type'       => 'boom_pump',
            'pump_vehicle_id' => $this->pump->id,
            'boom_length_m'   => 42,
            'status'          => 'scheduled',
        ]);

        $updatePayload = [
            'schedule_date'     => '2026-09-08',
            'pour_reference'    => 'Bridge Deck Pour',
            'site_id'           => $this->site->id,
            'pour_location'     => 'Span 3 & 4',
            'mix_design_id'     => $this->mixDesign->id,
            'planned_qty_m3'    => 45.0,
            'pump_type'         => 'boom_pump',
            'pump_vehicle_id'   => $this->pump->id,
            'boom_length_m'     => 42,
            'status'            => 'en_route',
            'actual_start_time' => '2026-09-07 09:30:00',
            'actual_end_time'   => '2026-09-07 11:30:00',
            'notes'             => 'Night pour scheduled',
        ];

        $response = $this->putJson(route('production.pump-deployments.update', $deployment->id), $updatePayload);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('mm_pump_boom_deployment_schedule', [
            'id'             => $deployment->id,
            'planned_qty_m3' => 45.0,
            'pour_location'  => 'Span 3 & 4',
            'status'         => 'en_route',
        ]);

        // Status advance to delayed with reason notes
        $delayResponse = $this->patchJson(route('production.pump-deployments.update-status', $deployment->id), [
            'status' => 'delayed',
            'notes'  => 'Site access blocked by tower crane',
        ]);
        $delayResponse->assertStatus(200)->assertJson(['success' => true]);

        $fresh = $deployment->fresh();
        $this->assertEquals('delayed', $fresh->status);
        $this->assertEquals('Site access blocked by tower crane', $fresh->notes);
    }

    public function test_pump_boom_deployment_validates_pump_and_operator_overlap()
    {
        PumpBoomDeploymentSchedule::create([
            'plant_id'          => $this->plant->id,
            'schedule_date'     => '2026-09-08',
            'pour_reference'    => 'Job 1',
            'site_id'           => $this->site->id,
            'pour_location'     => 'East Wing',
            'mix_design_id'     => $this->mixDesign->id,
            'planned_qty_m3'    => 40.0,
            'pump_type'         => 'boom_pump',
            'pump_vehicle_id'   => $this->pump->id,
            'boom_length_m'     => 36,
            'operator_id'       => $this->operator->id,
            'setup_start_time'  => '2026-09-08 08:00:00',
            'planned_end_time'  => '2026-09-08 12:00:00',
            'status'            => 'scheduled',
        ]);

        // Overlapping pour for same pump
        $pumpConflictPayload = [
            'schedule_date'     => '2026-09-08',
            'pour_reference'    => 'Job 2',
            'site_id'           => $this->site->id,
            'pour_location'     => 'West Wing',
            'mix_design_id'     => $this->mixDesign->id,
            'planned_qty_m3'    => 30.0,
            'pump_type'         => 'boom_pump',
            'pump_vehicle_id'   => $this->pump->id,
            'boom_length_m'     => 36,
            'setup_start_time'  => '2026-09-08 10:00:00',
            'planned_end_time'  => '2026-09-08 14:00:00',
        ];

        $pumpResponse = $this->postJson(route('production.pump-deployments.store'), $pumpConflictPayload);
        $pumpResponse->assertStatus(422)
            ->assertJsonValidationErrors(['pump_vehicle_id']);

        // Overlapping pour for different pump but same operator
        $anotherPump = Machine::factory()->create([
            'plant_id'     => $this->plant->id,
            'registration' => 'PUMP-42M-02',
            'vehicle_type' => 'boom_pump',
            'capacity'     => 42.0,
        ]);

        $opConflictPayload = [
            'schedule_date'     => '2026-09-08',
            'pour_reference'    => 'Job 3',
            'site_id'           => $this->site->id,
            'pour_location'     => 'North Wing',
            'mix_design_id'     => $this->mixDesign->id,
            'planned_qty_m3'    => 30.0,
            'pump_type'         => 'boom_pump',
            'pump_vehicle_id'   => $anotherPump->id,
            'boom_length_m'     => 42,
            'operator_id'       => $this->operator->id,
            'setup_start_time'  => '2026-09-08 11:00:00',
            'planned_end_time'  => '2026-09-08 15:00:00',
        ];

        $opResponse = $this->postJson(route('production.pump-deployments.store'), $opConflictPayload);
        $opResponse->assertStatus(422)
            ->assertJsonValidationErrors(['operator_id']);
    }

    public function test_pump_boom_deployment_destroy()
    {
        $deployment = PumpBoomDeploymentSchedule::create([
            'plant_id'        => $this->plant->id,
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Pour to Remove',
            'site_id'         => $this->site->id,
            'pour_location'   => 'Pavement',
            'mix_design_id'   => $this->mixDesign->id,
            'planned_qty_m3'  => 10.0,
            'pump_type'       => 'direct_pour',
            'pump_no'         => 'Chute Pour',
            'status'          => 'scheduled',
        ]);

        $response = $this->deleteJson(route('production.pump-deployments.destroy', $deployment->id));
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSoftDeleted('mm_pump_boom_deployment_schedule', ['id' => $deployment->id]);
    }

    public function test_concrete_batching_schedule_rejects_exceeding_sales_order_remaining_volume()
    {
        $so = SalesOrder::factory()->create([
            'plant_id'    => $this->plant->id,
            'customer_id' => $this->site->customer_id ?? null,
            'total_qty'   => 10.0,
            'rate'        => 4500,
        ]);

        // Existing dispatch of 6 m3
        Dispatch::create([
            'plant_id'          => $this->plant->id,
            'sales_order_id'    => $so->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'delivered_qty'     => 6.0,
            'dispatch_status'   => 'Delivered',
            'payment_mode'      => 'credit',
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '10',
        ]);

        // Attempting to schedule 5 m3 (only 4 m3 remains)
        $payloadExcess = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Pour Capacity Check',
            'site_id'         => $this->site->id,
            'mix_design_id'   => $this->mixDesign->id,
            'qty_m3'          => 5.0,
            'order_volume_m3' => 10.0,
            'pump_type'       => 'direct_pour',
            'sales_order_id'  => $so->id,
            'status'          => 'scheduled',
        ];

        $responseExcess = $this->postJson(route('production.batching-schedules.store'), $payloadExcess);
        $responseExcess->assertStatus(422)
            ->assertJsonValidationErrors(['qty_m3']);

        // Scheduling exact remaining volume 4.0 m3 should succeed
        $payloadValid = array_merge($payloadExcess, ['qty_m3' => 4.0]);
        $responseValid = $this->postJson(route('production.batching-schedules.store'), $payloadValid);
        $responseValid->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_concrete_batching_schedule_rejects_truck_on_active_dispatch()
    {
        // Truck is currently out on an active dispatch
        Dispatch::create([
            'plant_id'          => $this->plant->id,
            'truck_id'          => $this->truck->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'delivered_qty'     => 6.0,
            'dispatch_time'     => '2026-09-08 10:00:00',
            'dispatch_status'   => 'In Transit',
            'payment_mode'      => 'credit',
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '11',
        ]);

        // Attempt to allocate same truck at overlapping time 10:30
        $payload = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Overlap Dispatch Check',
            'site_id'         => $this->site->id,
            'mix_design_id'   => $this->mixDesign->id,
            'qty_m3'          => 6.0,
            'order_volume_m3' => 12.0,
            'vehicle_id'      => $this->truck->id,
            'pump_type'       => 'direct_pour',
            'batching_time'   => '2026-09-08 10:30:00',
            'status'          => 'scheduled',
        ];

        $response = $this->postJson(route('production.batching-schedules.store'), $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_dispatch_update_synchronizes_batching_schedule_status()
    {
        $schedule = ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Sync Test Pour',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 12.0,
            'remaining_volume_m3' => 6.0,
            'pump_type'           => 'direct_pour',
            'status'              => 'scheduled',
        ]);

        $dispatch = Dispatch::create([
            'plant_id'          => $this->plant->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'delivered_qty'     => 6.0,
            'dispatch_status'   => 'Draft',
            'payment_mode'      => 'credit',
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '12',
        ]);

        $schedule->update(['dispatch_id' => $dispatch->id]);

        // Update dispatch to 'In Transit'
        $dispatch->update([
            'dispatch_status' => 'In Transit',
            'dispatch_time'   => '2026-09-08 11:15:00',
        ]);

        $freshSchedule = $schedule->fresh();
        $this->assertEquals('in_transit', $freshSchedule->status);
        $this->assertNotNull($freshSchedule->dispatch_time);

        // Update dispatch to 'Delivered'
        $dispatch->update([
            'dispatch_status' => 'Delivered',
            'delivery_time'   => '2026-09-08 12:00:00',
        ]);

        $freshSchedule2 = $schedule->fresh();
        $this->assertEquals('completed', $freshSchedule2->status);
        $this->assertNotNull($freshSchedule2->unloading_end);
    }

    public function test_concrete_batching_schedule_store_with_existing_dispatch_links_and_syncs()
    {
        $existingDispatch = Dispatch::create([
            'plant_id'          => $this->plant->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'truck_id'          => $this->truck->id,
            'driver_id'         => $this->driver->id,
            'delivered_qty'     => 7.5,
            'dispatch_status'   => 'In Transit',
            'dispatch_time'     => '2026-09-08 14:00:00',
            'payment_mode'      => 'credit',
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '99',
            'dispatch_reference'=> 'AUTOFILL-POUR-99',
        ]);

        $payload = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'AUTOFILL-POUR-99',
            'site_id'         => $this->site->id,
            'mix_design_id'   => $this->mixDesign->id,
            'qty_m3'          => 7.5,
            'order_volume_m3' => 30.0,
            'vehicle_id'      => $this->truck->id,
            'driver_id'       => $this->driver->id,
            'pump_type'       => 'direct_pour',
            'dispatch_id'     => $existingDispatch->id,
            'status'          => 'in_transit',
            'dispatch_time'   => '2026-09-08 14:00:00',
        ];

        $response = $this->postJson(route('production.batching-schedules.store'), $payload);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('mm_concrete_batching_schedules', [
            'dispatch_id'    => $existingDispatch->id,
            'qty_m3'         => 7.5,
            'status'         => 'in_transit',
        ]);

        // Ensure no new dispatch was created; count of dispatches with no 99 should remain 1
        $this->assertEquals(1, Dispatch::where('plant_id', $this->plant->id)->where('dispatch_no', '99')->count());
        
        $existingDispatch->refresh();
        $this->assertEquals('In Transit', $existingDispatch->dispatch_status);
        $this->assertNotNull($existingDispatch->batch_id);
    }

    public function test_concrete_batching_schedule_get_data_with_filters_and_kpis()
    {
        ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Kpi Pour 1',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 12.0,
            'remaining_volume_m3' => 6.0,
            'pump_type'           => 'direct_pour',
            'status'              => 'batching',
        ]);

        ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Kpi Pour 2',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 8.0,
            'order_volume_m3'     => 16.0,
            'remaining_volume_m3' => 8.0,
            'pump_type'           => 'boom_pump',
            'status'              => 'completed',
        ]);

        $response = $this->getJson(route('production.batching-schedules.data', [
            'schedule_date'  => '2026-09-08',
            'status'         => 'all',
            'pour_reference' => 'Kpi Pour',
            'site_id'        => $this->site->id,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'schedules',
                'pours',
                'metrics' => [
                    'total_scheduled_m3',
                    'total_delivered_m3',
                    'active_in_transit_tms',
                    'active_pouring_tms',
                    'hydration_warning_count',
                ]
            ]);

        $this->assertEquals(2, count($response->json('schedules')));
        $this->assertEquals(14.0, (float) $response->json('metrics.total_scheduled_m3'));
        $this->assertEquals(8.0, (float) $response->json('metrics.total_delivered_m3'));
    }

    public function test_concrete_batching_schedule_dropdowns_includes_active_dispatches_with_formatted_labels()
    {
        $batch = Batch::create([
            'plant_id'   => $this->plant->id,
            'batch_no'   => 555,
            'batch_size' => 6.0,
            'status'     => Batch::STATUS_LOADING,
        ]);

        $dispatch = Dispatch::create([
            'plant_id'          => $this->plant->id,
            'batch_id'          => $batch->id,
            'truck_id'          => $this->truck->id,
            'driver_id'         => $this->driver->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'delivered_qty'     => 6.0,
            'dispatch_status'   => 'Loading',
            'delivery_time'     => '2026-09-08 12:00:00',
            'payment_mode'      => 'credit',
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '555',
            'dispatch_reference'=> 'SITE-TICKET-555',
        ]);

        $response = $this->getJson(route('production.batching-schedules.dropdowns'));
        $response->assertStatus(200)
            ->assertJsonStructure(['sites', 'mixDesigns', 'vehicles', 'drivers', 'dispatches', 'pumpTypes']);

        $dispatches = $response->json('dispatches');
        $this->assertNotEmpty($dispatches);

        $match = collect($dispatches)->firstWhere('id', $dispatch->id);
        $this->assertNotNull($match);
        $this->assertStringContainsString('555', $match['label']);
        $this->assertEquals(555, $match['batch_no']);
        $this->assertEquals('TM-01-AB-1234', $match['vehicle_reg']);
    }

    public function test_concrete_batching_schedule_validates_driver_overlap()
    {
        $anotherTruck = Machine::factory()->create([
            'plant_id'     => $this->plant->id,
            'registration' => 'TM-02-CD-5678',
            'vehicle_type' => 'transit_mixer',
            'capacity'     => 7.0,
        ]);

        ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Driver Overlap 1',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 12.0,
            'remaining_volume_m3' => 6.0,
            'vehicle_id'          => $this->truck->id,
            'driver_id'           => $this->driver->id,
            'pump_type'           => 'direct_pour',
            'batching_time'       => '2026-09-08 10:00:00',
            'eta_site'            => '2026-09-08 10:45:00',
            'status'              => 'in_transit',
        ]);

        // Attempting to assign same driver to a different truck in overlapping window
        $conflictingPayload = [
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Driver Overlap 2',
            'site_id'         => $this->site->id,
            'mix_design_id'   => $this->mixDesign->id,
            'qty_m3'          => 6.0,
            'order_volume_m3' => 12.0,
            'vehicle_id'      => $anotherTruck->id,
            'driver_id'       => $this->driver->id,
            'pump_type'       => 'direct_pour',
            'batching_time'   => '2026-09-08 10:20:00',
            'status'          => 'scheduled',
        ];

        $response = $this->postJson(route('production.batching-schedules.store'), $conflictingPayload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_concrete_batching_schedule_update_with_linked_dispatch_syncs_dispatch_details()
    {
        $dispatch = Dispatch::create([
            'plant_id'          => $this->plant->id,
            'load_site_id'      => $this->plant->id,
            'unload_site_id'    => $this->site->id,
            'mixdesign_id'      => $this->mixDesign->id,
            'delivered_qty'     => 6.0,
            'dispatch_status'   => 'Draft',
            'payment_mode'      => 'credit',
            'prefix'            => 'DP-2627-',
            'dispatch_no'       => '88',
        ]);

        $schedule = ConcreteBatchingSchedule::create([
            'plant_id'            => $this->plant->id,
            'schedule_date'       => '2026-09-08',
            'pour_reference'      => 'Sync Dispatch Update Pour',
            'site_id'             => $this->site->id,
            'mix_design_id'       => $this->mixDesign->id,
            'qty_m3'              => 6.0,
            'order_volume_m3'     => 24.0,
            'remaining_volume_m3' => 18.0,
            'pump_type'           => 'direct_pour',
            'dispatch_id'         => $dispatch->id,
            'status'              => 'scheduled',
        ]);

        $updatePayload = [
            'schedule_date'    => '2026-09-08',
            'pour_reference'   => 'Sync Dispatch Update Pour',
            'site_id'          => $this->site->id,
            'mix_design_id'    => $this->mixDesign->id,
            'qty_m3'           => 7.5,
            'order_volume_m3'  => 24.0,
            'vehicle_id'       => $this->truck->id,
            'driver_id'        => $this->driver->id,
            'pump_type'        => 'direct_pour',
            'dispatch_id'      => $dispatch->id,
            'batching_time'    => '2026-09-08 14:00:00',
            'dispatch_time'    => '2026-09-08 14:30:00',
            'eta_site'         => '2026-09-08 15:15:00',
            'unloading_start'  => '2026-09-08 15:30:00',
            'unloading_end'    => '2026-09-08 16:15:00',
            'status'           => 'completed',
        ];

        $response = $this->putJson(route('production.batching-schedules.update', $schedule->id), $updatePayload);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $dispatch->refresh();
        $this->assertEquals(7.5, (float) $dispatch->delivered_qty);
        $this->assertEquals('Delivered', $dispatch->dispatch_status);
        $this->assertEquals($this->truck->id, $dispatch->truck_id);
        $this->assertEquals($this->driver->id, $dispatch->driver_id);
    }

    public function test_pump_boom_deployment_get_data_with_filters_and_summary_kpis()
    {
        PumpBoomDeploymentSchedule::create([
            'plant_id'         => $this->plant->id,
            'schedule_date'    => '2026-09-08',
            'pour_reference'   => 'Pump KPI Pour 1',
            'site_id'          => $this->site->id,
            'pour_location'    => 'Sector 1',
            'mix_design_id'    => $this->mixDesign->id,
            'planned_qty_m3'   => 50.0,
            'pump_type'        => 'boom_pump',
            'pump_vehicle_id'  => $this->pump->id,
            'boom_length_m'    => 36,
            'operator_id'      => $this->operator->id,
            'status'           => 'in_progress',
        ]);

        PumpBoomDeploymentSchedule::create([
            'plant_id'         => $this->plant->id,
            'schedule_date'    => '2026-09-08',
            'pour_reference'   => 'Pump KPI Pour 2',
            'site_id'          => $this->site->id,
            'pour_location'    => 'Sector 2',
            'mix_design_id'    => $this->mixDesign->id,
            'planned_qty_m3'   => 30.0,
            'pump_type'        => 'line_pump',
            'status'           => 'completed',
        ]);

        $response = $this->getJson(route('production.pump-deployments.data', [
            'schedule_date'   => '2026-09-08',
            'status'          => 'all',
            'pour_reference'  => 'Pump KPI',
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'deployments',
                'metrics' => [
                    'total_deployments',
                    'total_planned_m3',
                    'active_pumping_count',
                    'setup_in_progress',
                    'completed_deployments',
                ]
            ]);

        $this->assertEquals(2, count($response->json('deployments')));
        $this->assertEquals(2, $response->json('metrics.total_deployments'));
        $this->assertEquals(80.0, (float) $response->json('metrics.total_planned_m3'));
    }

    public function test_pump_boom_deployment_validates_time_chronology()
    {
        // Setup start cannot be later than setup end
        $invalidSetupPayload = [
            'schedule_date'     => '2026-09-08',
            'pour_reference'    => 'Setup Time Error Pour',
            'site_id'           => $this->site->id,
            'pour_location'     => 'Basement 1',
            'mix_design_id'     => $this->mixDesign->id,
            'planned_qty_m3'    => 30.0,
            'pump_type'         => 'boom_pump',
            'pump_vehicle_id'   => $this->pump->id,
            'boom_length_m'     => 36,
            'setup_start_time'  => '2026-09-08 10:00:00',
            'setup_end_time'    => '2026-09-08 09:30:00', // earlier than start!
            'operator_id'       => $this->operator->id,
        ];

        $response = $this->postJson(route('production.pump-deployments.store'), $invalidSetupPayload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['setup_start_time']);

        // Actual start cannot be later than actual end
        $invalidActualPayload = [
            'schedule_date'     => '2026-09-08',
            'pour_reference'    => 'Actual Time Error Pour',
            'site_id'           => $this->site->id,
            'pour_location'     => 'Basement 2',
            'mix_design_id'     => $this->mixDesign->id,
            'planned_qty_m3'    => 30.0,
            'pump_type'         => 'boom_pump',
            'pump_vehicle_id'   => $this->pump->id,
            'boom_length_m'     => 36,
            'actual_start_time' => '2026-09-08 14:00:00',
            'actual_end_time'   => '2026-09-08 13:00:00', // earlier than start!
            'operator_id'       => $this->operator->id,
        ];

        $response2 = $this->postJson(route('production.pump-deployments.store'), $invalidActualPayload);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['actual_start_time']);
    }

    public function test_pump_boom_deployment_full_status_lifecycle()
    {
        $deployment = PumpBoomDeploymentSchedule::create([
            'plant_id'        => $this->plant->id,
            'schedule_date'   => '2026-09-08',
            'pour_reference'  => 'Lifecycle Pour',
            'site_id'         => $this->site->id,
            'pour_location'   => 'Zone A',
            'mix_design_id'   => $this->mixDesign->id,
            'planned_qty_m3'  => 40.0,
            'pump_type'       => 'boom_pump',
            'pump_vehicle_id' => $this->pump->id,
            'boom_length_m'   => 36,
            'operator_id'     => $this->operator->id,
            'status'          => 'scheduled',
        ]);

        $statuses = ['en_route', 'setup', 'ready', 'in_progress', 'washout', 'completed'];

        foreach ($statuses as $status) {
            $resp = $this->patchJson(route('production.pump-deployments.update-status', $deployment->id), [
                'status' => $status,
            ]);
            $resp->assertStatus(200)->assertJson(['success' => true]);
            $this->assertEquals($status, $deployment->fresh()->status);
        }
    }
}

