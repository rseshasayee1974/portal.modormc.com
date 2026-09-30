<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string) $e); exit(1); });

use App\Http\Controllers\EntityContextController;
use App\Http\Controllers\ERPDashboardController;
use App\Http\Middleware\SetEntityContext;
use App\Models\User;
use App\Services\LoginDestination;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

config(['database.default' => 'workspace_test', 'database.connections.workspace_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);

function checkWorkspace(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    'mm_entities' => 'id INTEGER PRIMARY KEY, is_suspended INTEGER, deleted_at TEXT',
    'mm_plants' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, is_active INTEGER, deleted_at TEXT',
    'mm_entity_users' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, plant_id INTEGER, user_id INTEGER, deleted_at TEXT',
] as $table => $columns) DB::statement("CREATE TABLE $table ($columns)");

$user = new class extends User {
    public function isSystemAdmin(): bool { return false; }
};
$user->forceFill(['id' => 77, 'last_visit_page' => 'settings/roles?tab=permissions']);
Auth::setUser($user);
$request = Request::create('https://modormc.com/dashboard');
$request->setLaravelSession(app('session.store'));
$request->setUserResolver(fn () => $user);
app()->instance('request', $request);
$ctx = app(PlantContextService::class);
$destination = app(LoginDestination::class);

checkWorkspace(!$ctx->hasWorkspaces($user), 'Empty account must have no workspace.');
checkWorkspace($destination->resolve($request) === '/settings/roles?tab=permissions', 'Last visit was not restored.');
session(['url.intended' => 'https://modormc.com/master/entities']);
checkWorkspace($destination->resolve($request) === '/master/entities', 'Intended URL must take priority.');
checkWorkspace(!session()->has('url.intended'), 'Intended URL must be consumed.');
foreach (['/context/selectentity', 'https://other.example/dashboard', '//other.example', '/login', '/logout', '/verifyotp'] as $invalid) {
    session(['url.intended' => $invalid]);
    checkWorkspace($destination->resolve($request) === '/settings/roles?tab=permissions', 'Invalid destination accepted: '.$invalid);
}
$user->last_visit_page = '/context/selectentity';
checkWorkspace($destination->resolve($request) === '/dashboard', 'Selector must not create a redirect loop.');
$user->last_visit_page = 'settings/roles';
checkWorkspace((new EntityContextController)->index()->getTargetUrl() === url('/settings/roles'), 'Empty selector did not skip to last visit.');
session(['active_entity_id' => 999, 'active_plant_id' => 999]);
$response = (new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace($response->getContent() === 'dashboard' && !session('active_plant_id'), 'Empty account was redirected or retained stale context.');
$resolvePlant = new ReflectionMethod(ERPDashboardController::class, 'resolvePlantId');
checkWorkspace($resolvePlant->invoke(new ERPDashboardController) === null, 'Dashboard selected an unrelated plant.');

DB::table('mm_entities')->insert(['id' => 1, 'is_suspended' => 0]);
DB::table('mm_plants')->insert(['id' => 1, 'entity_id' => 1, 'is_active' => 1]);
checkWorkspace(!$ctx->hasWorkspaces($user), 'Unassigned user gained a workspace.');
checkWorkspace($ctx->hasWorkspaces($user, true), 'Admin did not see workspace.');
DB::table('mm_entity_users')->insert(['id' => 1, 'user_id' => 77, 'entity_id' => 1, 'plant_id' => null]);
checkWorkspace($ctx->hasWorkspaces($user), 'Entity-wide assignment was not recognized.');
DB::table('mm_plants')->insert(['id' => 2, 'entity_id' => 1, 'is_active' => 1]);
$response = (new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace($response->isRedirect(route('entity-context.index')), 'Multiple workspaces must still require selection.');
DB::table('mm_plants')->where('id', 2)->delete();
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => 2]);
checkWorkspace(!$ctx->hasWorkspaces($user), 'Nonexistent specific plant was accepted.');
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => 1]);
DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => 1]);
DB::table('mm_plants')->where('id', 1)->update(['is_active' => -1]);
checkWorkspace($ctx->hasWorkspaces($user), 'Restricted workspace must retain existing restriction flow.');
DB::table('mm_entity_users')->where('id', 1)->update(['deleted_at' => '2026-09-30']);
checkWorkspace(!$ctx->hasWorkspaces($user), 'Deleted assignment was accepted.');

echo "Workspace login checks passed.\n";
