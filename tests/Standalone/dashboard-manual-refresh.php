<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 16:59:00 Asia/Calcutta (UTC+05:30)
*/

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string) $e); exit(1); });

use App\Http\Controllers\ERPDashboardController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function checkManualDashboard(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// No report tables: a cold page must render without executing report queries.
config(['cache.default' => 'array', 'session.driver' => 'array',
    'database.default' => 'dashboard_refresh_test',
    'database.connections.dashboard_refresh_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ],
]);
Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));
session(['active_plant_id' => 1]);
$user = new User;
$user->id = 41;
auth()->setUser($user);
Cache::put('patrons.1', collect(), now()->addDays(7));
Cache::put('patrons.2', collect(), now()->addDays(7));
DB::enableQueryLog();
$controller = new ERPDashboardController;
$cacheKey = new ReflectionMethod($controller, 'getCacheKey');
$props = new ReflectionProperty(Inertia\Response::class, 'props');
$request = Request::create('/dashboard');
$key = $cacheKey->invoke($controller, 'full', 1, $request);

try {
    foreach (['index', 'analytics'] as $action) {
        $page = $props->getValue($controller->$action($request));
        checkManualDashboard($page['initialData']['generated_at'] === null, 'A cold page must show data as not loaded');
        checkManualDashboard(DB::getQueryLog() === [], 'Page navigation must not run report queries');
        $explicit = Request::create('/dashboard/data', 'GET', $page['filters']);
        checkManualDashboard($key === $cacheKey->invoke($controller, 'full', 1, $explicit), 'Default and explicit filters must share a cache key');
    }

    $snapshot = ['generated_at' => '2026-10-07T11:00:00+05:30', 'metrics' => ['sales_revenue' => 1234]];
    Cache::put($key, $snapshot, 300);
    foreach (['index', 'analytics'] as $action) {
        $page = $props->getValue($controller->$action(Request::create('/dashboard', 'GET', ['refresh' => true])));
        checkManualDashboard($page['initialData'] === $snapshot, 'Navigation must reuse the snapshot even with a refresh query parameter');
    }
    checkManualDashboard($controller->getData($request)->getData(true) === $snapshot, 'Data endpoint must reuse a matching snapshot');
    checkManualDashboard(DB::getQueryLog() === [], 'Warm navigation and cached API requests must not run report queries');

    session(['active_plant_id' => 2]);
    checkManualDashboard($props->getValue($controller->index($request))['initialData']['generated_at'] === null, 'Snapshots must be isolated by plant');
    session(['active_plant_id' => 1]);
    $user->id = 42;
    checkManualDashboard($props->getValue($controller->index($request))['initialData']['generated_at'] === null, 'Snapshots must be isolated by user');
    $user->id = 41;
    checkManualDashboard($key !== $cacheKey->invoke($controller, 'full', 1, Request::create('/dashboard', 'GET', ['patron_id' => 5])), 'Patron filters must be isolated');

    try {
        $controller->getData(Request::create('/dashboard/data', 'GET', ['refresh' => true]));
        throw new RuntimeException('Manual refresh must actually execute report queries');
    } catch (Illuminate\Database\QueryException $e) {
        checkManualDashboard(Cache::get($key) === $snapshot, 'A failed refresh must preserve the previous snapshot');
    }
    Carbon::setTestNow(now()->addMinutes(6));
    checkManualDashboard($props->getValue($controller->index($request))['initialData']['generated_at'] === null, 'Expired snapshots must not trigger report calculations');
    session()->forget('active_plant_id');
    checkManualDashboard($controller->getData($request)->getData(true)['generated_at'] === null, 'No plant must return an unloaded payload');
} finally {
    Carbon::setTestNow();
}

echo "Dashboard cold/warm navigation, filter cache matching, isolation, manual refresh, failure preservation and expiry checks passed.\n";
