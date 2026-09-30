<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string) $e); exit(1); });

use App\Services\Reports\{BatchingScheduleReportService, ExcelExportService, ReportServiceFactory};
use Illuminate\Support\Facades\DB;

config(['database.default' => 'batching_report_test', 'database.connections.batching_report_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'session.driver' => 'array', 'logging.default' => 'null']);
session(['active_plant_id' => 1]);
foreach ([
    'mm_concrete_batching_schedules' => 'id INTEGER PRIMARY KEY, plant_id INTEGER, schedule_date TEXT, pour_reference TEXT, site_id INTEGER, vehicle_id INTEGER, pump_vehicle_id INTEGER, mix_design_id INTEGER, qty_m3 REAL, status TEXT, batching_time TEXT, dispatch_time TEXT, eta_site TEXT, unloading_start TEXT, unloading_end TEXT, pump_type TEXT, deleted_at TEXT',
    'mm_sites' => 'id INTEGER PRIMARY KEY, plant_id INTEGER, name TEXT, deleted_at TEXT',
    'mm_machines' => 'id INTEGER PRIMARY KEY, plant_id INTEGER, registration TEXT, deleted_at TEXT',
    'mm_mix_designs' => 'id INTEGER PRIMARY KEY, plant_id INTEGER, design_name TEXT, deleted_at TEXT',
] as $table => $columns) DB::statement("CREATE TABLE $table ($columns)");
DB::table('mm_sites')->insert(['id' => 1, 'plant_id' => 1, 'name' => 'Site A']);
DB::table('mm_machines')->insert([
    ['id' => 1, 'plant_id' => 1, 'registration' => 'TRUCK-A'],
    ['id' => 2, 'plant_id' => 1, 'registration' => 'PUMP-A'],
]);
DB::table('mm_mix_designs')->insert(['id' => 1, 'plant_id' => 1, 'design_name' => 'M25']);
$base = ['plant_id' => 1, 'schedule_date' => '2026-09-30', 'pour_reference' => 'POUR-1', 'site_id' => 1, 'vehicle_id' => 1, 'pump_vehicle_id' => 2, 'mix_design_id' => 1, 'qty_m3' => 6, 'status' => 'scheduled', 'pump_type' => 'boom_pump'];
foreach ([[], ['site_id' => 2], ['vehicle_id' => 3], ['pump_vehicle_id' => 4], ['status' => 'cancelled'], ['plant_id' => 2], ['schedule_date' => '2026-10-01'], ['deleted_at' => '2026-09-30']] as $i => $override) {
    DB::table('mm_concrete_batching_schedules')->insert(['id' => $i + 1] + $override + $base);
}
$service = app(ReportServiceFactory::class)->make('batching_schedule');
$params = ['start' => '2026-09-30 12:00:00', 'end' => '2026-09-30 23:59:59'];
$check = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$all = $service->generate($params);
$check($all['total_schedules'] === 5 && $all['total_quantity'] === 24.0, 'Plant/date/deletion or cancelled-volume handling failed.');
foreach (['site_id' => 1, 'truck_id' => 1, 'pump_vehicle_id' => 2] as $field => $value) {
    $result = $service->generate([$field => $value] + $params);
    $check($result['total_schedules'] === 4 && $result['total_quantity'] === 18.0, "$field filter failed.");
}
$result = $service->generate(['site_id' => 1, 'truck_id' => 1, 'pump_vehicle_id' => 2] + $params);
$check($result['total_schedules'] === 2 && $result['total_quantity'] === 6.0, 'Combined filters failed.');
$row = $result['transactions']->first();
$check($row['site'] === 'Site A' && $row['truck'] === 'TRUCK-A' && $row['pump'] === 'PUMP-A' && $row['mix_design'] === 'M25', 'Related names failed.');
$empty = $service->generate(['pump_vehicle_id' => 999] + $params);
$check($empty['total_schedules'] === 0 && $empty['total_quantity'] === 0.0, 'Empty report failed.');
$pdf = BatchingScheduleReportService::pdfColumns($result);
$check($pdf['fields'][7] === 'quantity' && $pdf['totals']['quantity'] === 6.0, 'PDF columns or totals failed.');
$pdfBytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.generic_report', [
    'target_name' => 'Batching Schedule Report', 'start' => $params['start'], 'end' => $params['end'],
    'plant' => null, 'patron' => null, 'transactions' => $result['transactions'],
] + $pdf)->setPaper('a4', 'landscape')->output();
$check(str_starts_with($pdfBytes, '%PDF-'), 'PDF generation failed.');
$sheet = app(ExcelExportService::class)->generateExcelReport('batching_schedule', $params['start'], $params['end'], $result)->getActiveSheet();
$values = $sheet->toArray();
$check(count(array_filter($values, fn ($r) => in_array('TRUCK-A', $r, true) && in_array('PUMP-A', $r, true))) === 2, 'Excel schedule rows failed.');
$check(count(array_filter($values, fn ($r) => in_array('Total (excluding cancelled)', $r, true) && in_array(6, $r))) === 1, 'Excel volume total failed.');
echo "Batching schedule report passed: filters, plant/date isolation, cancelled totals, empty results, related names and export columns.\n";
