<?php
/*
Author: Cipher
Created: 2026-11-06 10:54:13 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Services\Reports\OverallReportService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class OverallReportDispatchTest extends TestCase
{
    public function test_dispatch_quantities_and_template_totals_use_the_same_values(): void
    {
        config([
            'database.default' => 'overall_report_test',
            'database.connections.overall_report_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, batch_no TEXT, batch_size REAL, sales_order_id INTEGER, plant_id INTEGER, created_at TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY, plant_id INTEGER, batch_id INTEGER, customer_id INTEGER, truck_id INTEGER, driver_id INTEGER, unload_site_id INTEGER, delivered_qty REAL, net_weight REAL, load_total_amount REAL, pump_charges REAL, dispatch_status TEXT, dispatch_time TEXT, created_at TEXT, deleted_at TEXT)');
        DB::table('mm_batches')->insert(['id' => 1, 'batch_no' => 'B1', 'batch_size' => 6, 'plant_id' => 1, 'created_at' => '2026-11-06 09:00:00']);
        $base = ['plant_id' => 1, 'batch_id' => 1, 'dispatch_time' => '2026-11-06 10:00:00', 'created_at' => '2026-11-06 10:00:00', 'pump_charges' => 0];
        foreach ([
            $base + ['id' => 1, 'delivered_qty' => null, 'net_weight' => 12, 'load_total_amount' => 1200],
            $base + ['id' => 2, 'delivered_qty' => 2.5, 'net_weight' => 5, 'load_total_amount' => 500],
            $base + ['id' => 3, 'delivered_qty' => 0, 'net_weight' => 0, 'load_total_amount' => 0],
            $base + ['id' => 4, 'delivered_qty' => 99, 'net_weight' => 99, 'load_total_amount' => 9999, 'dispatch_status' => 'Cancelled'],
            array_replace($base, ['id' => 5, 'plant_id' => 2, 'delivered_qty' => 99, 'net_weight' => 99, 'load_total_amount' => 9999]),
        ] as $row) {
            DB::table('mm_dispatches')->insert($row);
        }

        $report = app(OverallReportService::class)->generate(['plant_id' => 1, 'start' => '2026-11-06', 'end' => '2026-11-06']);
        $rows = collect($report['dispatches']['list'])->keyBy('id');
        $this->assertCount(3, $rows);
        $this->assertSame(6.0, $rows[1]['delivered_qty']);
        $this->assertSame(2.5, $rows[2]['delivered_qty']);
        $this->assertSame(0.0, $rows[3]['delivered_qty']);
        $this->assertSame(8.5, $report['dispatches']['delivered_qty']);
        $this->assertSame(17.0, $report['dispatches']['net_weight']);
        $this->assertSame(1700.0, $report['dispatches']['total_value']);
        $this->assertSame(8.5, $report['executive_summary']['total_dispatched_volume']);
        $this->assertSame($report['operations']['total_dispatched_m3'], $report['dispatches']['delivered_qty']);

        $utcReport = app(OverallReportService::class)->generate(['plant_id' => 1, 'start' => '2026-11-06T04:30:00Z', 'end' => '2026-11-06T04:30:00Z']);
        $this->assertSame(8.5, $utcReport['dispatches']['delivered_qty']);
        $this->assertSame(3, $utcReport['dispatches']['count']);

        $html = view('reports.overall_report', $report)->render();
        $this->assertStringContainsString('Total Dispatched: 8.50 m³ | Net Weight: 17.00 T', $html);
        $this->assertStringContainsString('1,700.00', $html);
    }
}
