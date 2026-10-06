<?php
/*
Author: ragul-onemodo
Created: 2026-10-06 12:17:22 Asia/Calcutta (UTC+05:30)
*/

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string) $e); exit(1); });

use App\Helpers\DateTimeHelper;
use App\Http\Controllers\ERPDashboardController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function checkDashboardDate(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

config(['database.default' => 'dashboard_date_test', 'database.connections.dashboard_date_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'session.driver' => 'array']);
DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY, plant_id INTEGER, customer_id INTEGER, dispatch_time TEXT, deleted_at TEXT)');
foreach (['09:29:59', '09:30:00', '10:45:00', '12:00:00', '12:00:01'] as $index => $time) {
    DB::table('mm_dispatches')->insert(['id' => $index + 1, 'plant_id' => 1, 'customer_id' => 1, 'dispatch_time' => '2026-10-06 ' . $time]);
}

$controller = new ERPDashboardController;
$resolve = new ReflectionMethod($controller, 'resolveDateRange');
$dispatchQuery = new ReflectionMethod($controller, 'dispatchQuery');
Carbon::setTestNow(Carbon::parse('2026-10-06T06:30:00Z'));
try {
    DateTimeHelper::inTimezone('Asia/Kolkata', function () use ($controller, $resolve, $dispatchQuery) {
        [$start, $end] = $resolve->invoke($controller, '2026-10-06 09:30:00', '2026-10-06 12:00:00');
        checkDashboardDate($start->format('H:i:s') === '09:30:00' && $end->format('H:i:s') === '12:00:00', 'Selected time boundaries must be preserved');
        checkDashboardDate($dispatchQuery->invoke($controller, 1, $start, $end)->pluck('id')->all() === [2, 3, 4], 'Only dispatches within the selected times should be included, including both boundaries');

        [$start, $end] = $resolve->invoke($controller, '2026-10-06', '2026-10-06');
        checkDashboardDate($start->format('H:i:s') === '00:00:00' && $end->format('H:i:s') === '23:59:59', 'Date-only input must cover complete days');
        checkDashboardDate($dispatchQuery->invoke($controller, 1, $start, $end)->count() === 5, 'Date-only input must include all dispatch times');

        [$start, $end] = $resolve->invoke($controller, '2026-10-06 12:00:00', '2026-10-06 09:30:00');
        checkDashboardDate($start->format('H:i:s') === '09:30:00' && $end->format('H:i:s') === '12:00:00', 'Reversed time boundaries must preserve the chosen times');
        [$start, $end] = $resolve->invoke($controller, '2026-10-06', '2026-10-05');
        checkDashboardDate($start->toDateTimeString() === '2026-10-05 00:00:00' && $end->toDateTimeString() === '2026-10-06 23:59:59', 'Reversed date-only inputs must cover both complete days');

        [$start, $end] = $resolve->invoke($controller, '2026-10-06T04:00:00Z', '2026-10-06T06:30:00Z');
        checkDashboardDate($start->format('H:i:s') === '09:30:00' && $end->format('H:i:s') === '12:00:00', 'Offset timestamps must use the entity timezone for database filtering');
        [$start, $end] = $resolve->invoke($controller, null, null);
        checkDashboardDate($start->toDateTimeString() === '2026-09-06 00:00:00' && $end->toDateTimeString() === '2026-10-06 23:59:59', 'Default date range must match the report module');
        try { $resolve->invoke($controller, 'invalid-date', '2026-10-06'); throw new RuntimeException('Invalid date accepted'); }
        catch (ValidationException $e) { checkDashboardDate(isset($e->errors()['start_date']), 'Invalid date must return a field validation error'); }
    });
} finally {
    Carbon::setTestNow();
}

echo "Dashboard date/time bounds, timestamp queries, whole-day compatibility, timezone conversion and validation checks passed.\n";
