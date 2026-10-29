<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:34:18 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Machine, MachineTracker, Plant, User};
use App\Services\Reports\{ExcelExportService, MachineTrackerReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

class MachineDailyTrackerTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $this->plant->entity->update(['time_zone' => 'Asia/Kolkata']);
        $this->machine = Machine::factory()->create(['plant_id' => $this->plant->id, 'is_active' => true]);
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession([
            'active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'machine_id' => $this->machine->id, 'opening' => '2026-10-15 08:00:00',
            'odometer_start' => 100, 'odometer_end' => 140.25, 'shift' => 1,
        ], $extra);
    }

    private function tracker(array $extra = []): MachineTracker
    {
        return MachineTracker::create(array_merge($this->payload(), [
            'plant_id' => $this->plant->id, 'company_id' => $this->plant->entity_id,
            'eb_start' => 0, 'eb_close' => 0,
        ], $extra));
    }

    private function report(array $extra = []): array
    {
        return app(MachineTrackerReportService::class)->generate(array_merge([
            'start' => '2026-10-15 00:00:00', 'end' => '2026-10-15 23:59:59',
        ], $extra));
    }

    public function test_daily_running_km_can_be_recorded_without_fuel_refill_data(): void
    {
        $this->post(route('machine-trackers.store'), $this->payload(['running_km' => 999]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $tracker = MachineTracker::firstOrFail();
        $this->assertEquals(40.25, $tracker->running_km);
        $this->assertEquals(0, $tracker->last_fuel_filled_km);
        $this->assertEquals(0, $tracker->fuel);
        $this->assertSame('2026-10-15', $tracker->log_date);
        $this->assertEquals(40.25, $tracker->toArray()['running_km']);
    }

    public function test_invalid_readings_are_rejected_without_saving(): void
    {
        foreach ([['odometer_start' => -1], ['odometer_end' => 90], ['odometer_start' => null],
            ['odometer_start' => 100.001], ['closing' => '2026-10-14 08:00:00']] as $invalid) {
            $this->post(route('machine-trackers.store'), $this->payload($invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('mm_machine_tracker', 0);
    }

    public function test_updates_recalculate_running_km_and_reject_decreasing_odometer(): void
    {
        $tracker = $this->tracker();
        $this->put(route('machine-trackers.update', $tracker), $this->payload(['odometer_end' => 160.50]))
            ->assertSessionHasNoErrors();
        $this->assertEquals(60.50, $tracker->fresh()->running_km);
        $this->put(route('machine-trackers.update', $tracker), $this->payload(['odometer_end' => 50]))
            ->assertSessionHasErrors('odometer_end');
        $this->assertEquals(60.50, $tracker->fresh()->running_km);
    }

    public function test_machine_must_belong_to_the_active_plant_and_log_date_is_required(): void
    {
        $otherPlant = Plant::factory()->create();
        $otherMachine = Machine::factory()->create(['plant_id' => $otherPlant->id]);
        $this->post(route('machine-trackers.store'), $this->payload(['machine_id' => $otherMachine->id]))
            ->assertSessionHasErrors('machine_id');
        $this->post(route('machine-trackers.store'), $this->payload(['opening' => null]))
            ->assertSessionHasErrors('opening');
    }

    public function test_daily_total_sums_each_log_instead_of_using_the_odometer_span(): void
    {
        $first = $this->tracker();
        $second = $this->tracker(['odometer_start' => 200, 'odometer_end' => 230, 'shift' => 2]);
        $this->tracker(['opening' => '2026-10-14 08:00:00', 'odometer_end' => 999]);
        $data = $this->report(['truck_id' => $this->machine->id]);
        $this->assertEquals(70.25, $data['total_km_run']);
        $this->assertEquals($first->running_km + $second->running_km, $data['total_km_run']);
        $this->assertCount(2, $data['transactions']);
        foreach ($data['transactions'] as $row) $this->assertSame($row['odometer_diff'], $row['running_km']);
    }

    public function test_machine_filter_plant_scope_and_deleted_logs_match_daily_totals(): void
    {
        $this->tracker();
        $otherMachine = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $this->tracker(['machine_id' => $otherMachine->id, 'odometer_end' => 200]);
        $this->tracker(['odometer_end' => 999])->delete();
        $otherPlant = Plant::factory()->create();
        $this->tracker(['plant_id' => $otherPlant->id, 'odometer_end' => 999]);
        $this->assertEquals(40.25, $this->report(['truck_id' => $this->machine->id])['total_km_run']);
        $this->assertEquals(140.25, $this->report()['total_km_run']);
    }

    public function test_legacy_invalid_readings_do_not_inflate_report_totals(): void
    {
        $this->tracker(['odometer_start' => 100, 'odometer_end' => 90]);
        $this->tracker(['odometer_start' => -100, 'odometer_end' => 20]);
        $this->assertEquals(0, $this->report()['total_km_run']);
        $this->assertSame(0.0, MachineTracker::meterDifference(null, 100));
    }

    public function test_legacy_records_use_created_date_when_opening_is_missing(): void
    {
        $tracker = $this->tracker(['opening' => null]);
        DB::table('mm_machine_tracker')->where('id', $tracker->id)->update(['created' => '2026-10-15 23:45:00']);
        $tracker->refresh();
        $this->assertSame('2026-10-15', $tracker->log_date);
        $this->assertEquals(40.25, $this->report()['total_km_run']);
    }

    public function test_index_exposes_the_same_running_km_as_the_report(): void
    {
        $tracker = $this->tracker();
        $this->get(route('machine-trackers.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) =>
            $page->component('MachineTrackers/Index')->where('trackers.0.running_km', $tracker->running_km)
                ->where('trackers.0.log_date', '2026-10-15')->etc());
    }

    public function test_excel_and_pdf_use_the_same_kilometer_total(): void
    {
        $this->tracker();
        $this->tracker(['odometer_start' => 200, 'odometer_end' => 230]);
        $data = $this->report();
        $columns = MachineTrackerReportService::pdfColumns($data);
        $this->assertEquals(70.25, $columns['totals']['odometer_diff']);
        $html = view('reports.generic_report', array_merge($data, $columns, [
            'plant' => $this->plant, 'patron' => null, 'target_name' => 'Daily Machine Tracker',
            'start' => '15-10-2026', 'end' => '15-10-2026',
        ]))->render();
        $this->assertStringContainsString('KM Run', $html);
        $this->assertStringContainsString('70.25', $html);
        $book = app(ExcelExportService::class)->generateExcelReport('machine_tracker', '2026-10-15', '2026-10-15', $data);
        $rows = $book->getActiveSheet()->toArray(null, true, false);
        $totals = array_values(array_filter($rows, fn ($row) => $row[0] === 'Total Summary'));
        $this->assertEquals(70.25, $totals[0][7]);
        $book->disconnectWorksheets();
    }

    public function test_another_plant_cannot_update_or_delete_the_log(): void
    {
        $tracker = $this->tracker();
        $other = Plant::factory()->create();
        $this->withSession(['active_plant_id' => $other->id]);
        $this->assertContains($this->put(route('machine-trackers.update', $tracker), $this->payload())->status(), [403, 404]);
        $this->assertContains($this->delete(route('machine-trackers.destroy', $tracker))->status(), [403, 404]);
        $this->assertNull(DB::table('mm_machine_tracker')->where('id', $tracker->id)->value('deleted_at'));
    }
}
