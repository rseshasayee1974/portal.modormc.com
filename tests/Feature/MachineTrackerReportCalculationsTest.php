<?php

/*
Author: Cipher
Created: 2026-10-27 12:57:15 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Services\PlantContextService;
use App\Services\Reports\ExcelExportService;
use App\Services\Reports\MachineTrackerReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MachineTrackerReportCalculationsTest extends TestCase
{
    public function test_view_pdf_and_excel_expose_individual_calculations_and_aligned_totals(): void
    {
        config(['database.default' => 'tracker_test', 'database.connections.tracker_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::create('mm_machine_tracker', function (Blueprint $table) {
            $table->id();
            foreach (['plant_id', 'machine_id', 'operator_id', 'opening', 'closing', 'created', 'modified',
                'odometer_start', 'odometer_end', 'hourmeter_start', 'hourmeter_end', 'eb_start', 'eb_close',
                'opening_hsd', 'closing_hsd', 'fuel', 'amount', 'last_fuel_filled_km', 'fuel_filled_km',
                'shift', 'operation_type', 'category', 'pump_name', 'notes'] as $field) $table->text($field)->nullable();
            $table->softDeletes();
        });
        $this->mock(PlantContextService::class, function ($mock) {
            $mock->shouldReceive('requirePlantId')->andReturn(1);
            $mock->shouldReceive('plantId')->andReturn(1);
        });
        DB::table('mm_machine_tracker')->insert([
            'plant_id' => 1, 'opening' => '2026-10-27 08:00:00', 'odometer_start' => 100, 'odometer_end' => 160,
            'hourmeter_start' => 10, 'hourmeter_end' => 14, 'eb_start' => 200, 'eb_close' => 230, 'fuel' => 20, 'amount' => 2000,
            'last_fuel_filled_km' => 455, 'fuel_filled_km' => 4455,
        ]);
        $report = app(MachineTrackerReportService::class)->generate([]);
        $row = $report['transactions']->sole();
        foreach (['odometer_diff' => 60, 'odo_mileage' => 3, 'hourmeter_diff' => 4,
            'hm_mileage' => 0.2, 'eb_units' => 30, 'last_fuel_filled_km' => 455, 'fuel_filled_km' => 4455, 'fuel_distance' => 4000] as $field => $expected) {
            $this->assertEquals($expected, $row[$field]);
            $this->assertArrayHasKey($field, $report['columns']);
        }
        $this->assertSame('100 → 160', $row['odometer_readings']);
        $pdf = MachineTrackerReportService::pdfColumns($report);
        $this->assertSame(array_keys($report['columns']), $pdf['fields']);
        $this->assertNotContains('hm_consumption', $pdf['fields']);
        $this->assertNotContains('Fuel / Hour (L/Hr)', $pdf['headers']);
        $this->assertNotContains('odometer_display', $pdf['fields']);
        foreach (['odometer_start' => 100, 'odometer_end' => 160, 'hourmeter_start' => 10,
            'hourmeter_end' => 14, 'eb_start' => 200, 'eb_close' => 230] as $field => $expected) {
            $this->assertContains($field, $pdf['fields']);
            $this->assertArrayHasKey($field, $report['columns']);
            $this->assertEquals($expected, $row[$field]);
        }
        foreach (['odometer_readings', 'hourmeter_readings', 'eb_readings'] as $field) {
            $this->assertNotContains($field, $pdf['fields']);
        }
        $this->assertEquals(60, $pdf['totals']['odometer_diff']);
        $this->assertEquals(4, $pdf['totals']['hourmeter_diff']);
        $this->assertEquals(30, $pdf['totals']['eb_units']);
        $workbook = app(ExcelExportService::class)->generateExcelReport('machine_tracker', null, null, $report);
        $rows = $workbook->getActiveSheet()->toArray(null, true, false);
        $headerIndex = null;
        foreach ($rows as $index => $cells) {
            if (in_array('Last Fuel Filled (KM)', $cells, true)) { $headerIndex = $index; break; }
        }
        $this->assertNotNull($headerIndex);
        $headers = $rows[$headerIndex];
        foreach (['Odometer Start' => 100, 'Odometer End' => 160, 'Hourmeter Start' => 10,
            'Hourmeter End' => 14, 'EB Start' => 200, 'EB Close' => 230,
            'Last Fuel Filled (KM)' => 455, 'Fuel Filled (KM)' => 4455, 'Distance (KM)' => 4000] as $label => $expected) {
            $index = array_search($label, $headers, true);
            $this->assertNotFalse($index);
            $this->assertEquals($expected, $rows[$headerIndex + 1][$index]);
        }
        $this->assertNotContains('Fuel / Hour (L/Hr)', $headers);
        foreach (['KM Run' => 60, 'Hours Run' => 4, 'EB Units' => 30, 'Fuel (Ltrs)' => 20, 'Fuel Amount (₹)' => 2000] as $label => $expected) {
            $column = array_search($label, $headers, true);
            $this->assertNotFalse($column);
            $this->assertEquals($expected, $rows[$headerIndex + 2][$column]);
        }
        foreach (['Last Fuel Filled (KM)', 'Fuel Filled (KM)', 'Distance (KM)'] as $label) {
            $column = array_search($label, $headers, true);
            $this->assertEmpty($rows[$headerIndex + 2][$column]);
        }
        $workbook->disconnectWorksheets();
        DB::table('mm_machine_tracker')->update(['fuel' => 0, 'odometer_end' => 50]);
        $emptyFuel = app(MachineTrackerReportService::class)->generate([])['transactions']->sole();
        $this->assertEquals(0, $emptyFuel['odo_mileage']);
        $this->assertEquals(0, $emptyFuel['hm_consumption']);
        $this->assertEquals(0, $emptyFuel['odometer_diff']);
        foreach ([[0, 0, 0], [100, 90, -10], [null, 200, null], [100, null, null], [100.25, 125.75, 25.5]] as [$last, $filled, $distance]) {
            DB::table('mm_machine_tracker')->update(['last_fuel_filled_km' => $last, 'fuel_filled_km' => $filled]);
            $reading = app(MachineTrackerReportService::class)->generate([])['transactions']->sole();
            $this->assertSame($distance === null ? null : (float) $distance, $reading['fuel_distance']);
        }
    }
}
