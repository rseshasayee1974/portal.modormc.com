<?php
// Author: Cipher
// Created: 2026-10-23 17:01:04 Asia/Calcutta (UTC+05:30)

// Run: php scripts/frontend-data-loading.test.php
putenv('CACHE_STORE=array');
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

config(['database.default' => 'frontend_test', 'database.connections.frontend_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'session.driver' => 'array']);
DB::statement('CREATE TABLE mm_roles (id INTEGER PRIMARY KEY, name TEXT, guard_name TEXT, deleted_at TEXT)');
DB::statement('CREATE TABLE mm_permissions (id INTEGER PRIMARY KEY, name TEXT, guard_name TEXT, deleted_at TEXT)');
DB::table('mm_roles')->insert(['id' => 1, 'name' => 'Operator', 'guard_name' => 'web']);
DB::table('mm_permissions')->insert(['id' => 1, 'name' => 'users.view', 'guard_name' => 'web']);

$controller = new class extends App\Http\Controllers\RoleController {
    // Authorization is covered separately; this test isolates prop evaluation.
    protected function authorizeModule(string $action, ?string $module = null): void {}
};
$response = $controller->index(Request::create('/settings/roles'));
$props = (new ReflectionProperty(Inertia\Response::class, 'props'))->getValue($response);
$request = Request::create('/settings/roles', 'GET', [], [], [], [
    'HTTP_X_INERTIA' => 'true',
    'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Roles/Index',
    'HTTP_X_INERTIA_PARTIAL_DATA' => 'roles,filters',
]);

function checkLoading(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

DB::connection()->enableQueryLog();
$partial = $response->resolveProperties($request, $props);
checkLoading(!array_key_exists('groupedPermissions', $partial), 'Partial reload must omit form permissions');
checkLoading(count(DB::getQueryLog()) === 0, 'Partial reload must not execute permission queries');
checkLoading($partial['roles']['total'] === 1, 'Partial reload must preserve paginated roles');

$full = $response->resolveProperties(Request::create('/settings/roles'), $props);
checkLoading($full['groupedPermissions']['users'][0]['name'] === 'users.view', 'Full visits must include form permissions');
checkLoading(count(DB::getQueryLog()) === 1, 'Full visit should fetch permissions once');
fwrite(STDOUT, "PASS: partial reload omits form data and executes zero permission queries; full visit includes permissions.\n");

DB::statement('CREATE TABLE mm_users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, created_at TEXT, deleted_at TEXT)');
DB::statement('CREATE TABLE mm_entities (id INTEGER PRIMARY KEY, legal_name TEXT, alias TEXT, deleted_at TEXT)');
DB::statement('CREATE TABLE mm_plants (id INTEGER PRIMARY KEY, name TEXT, entity_id INTEGER, is_active INTEGER, deleted_at TEXT)');
$user = (new App\Models\User)->forceFill(['id' => 1]);
$user->setRelation('roles', new Illuminate\Database\Eloquent\Collection([
    (new App\Models\Role)->forceFill(['id' => 1, 'name' => 'Saas Owner', 'guard_name' => 'web']),
]));
$usersController = new class extends App\Http\Controllers\UserController {
    protected function authorizeModule(string $action, ?string $module = null): void {}
};
$usersRequest = Request::create('/users');
$usersRequest->setUserResolver(fn () => $user);
$usersResponse = $usersController->index($usersRequest);
$usersProps = (new ReflectionProperty(Inertia\Response::class, 'props'))->getValue($usersResponse);
$usersRequest->headers->add([
    'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Users/Index',
    'X-Inertia-Partial-Data' => 'users,filters',
]);
DB::flushQueryLog();
$usersPartial = $usersResponse->resolveProperties($usersRequest, $usersProps);
checkLoading(!array_key_exists('entities', $usersPartial) && !array_key_exists('plants', $usersPartial) && !array_key_exists('userGroups', $usersPartial), 'User search must omit form dropdowns');
checkLoading(count(DB::getQueryLog()) === 0, 'User search must not query form dropdowns');
$usersFull = $usersResponse->resolveProperties(Request::create('/users'), $usersProps);
checkLoading(isset($usersFull['entities'], $usersFull['plants'], $usersFull['userGroups']), 'Full user visit must include form dropdowns');
checkLoading(count(DB::getQueryLog()) === 3, 'Full user visit must fetch each dropdown only once');
fwrite(STDOUT, "PASS: user search excludes dropdown data and queries; full visit fetches each collection once.\n");

$assetHtml = app(Illuminate\Foundation\Vite::class)
    ->useHotFile(storage_path('logs/performance-test-no-hot'))
    ->__invoke(['resources/js/app.js', 'resources/js/Pages/Auth/Login.vue'])->toHtml();
checkLoading(!str_contains($assetHtml, 'prefetch'), 'Login must not schedule blanket lazy-chunk prefetches');
fwrite(STDOUT, "PASS: production asset HTML does not schedule blanket feature prefetching.\n");
