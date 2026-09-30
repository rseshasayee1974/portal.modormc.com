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
$logoutRequest = Request::create('https://modormc.com/logout', 'POST');
$logoutRequest->setLaravelSession(app('session.store'));
$logoutRequest->setUserResolver(fn () => $user);
$logoutRoute = new Illuminate\Routing\Route('POST', 'logout', fn () => null);
$logoutRoute->name('logout');
$logoutRequest->setRouteResolver(fn () => $logoutRoute);
$response = (new SetEntityContext)->handle($logoutRequest, fn () => response('logout reached'));
checkWorkspace($response->getContent() === 'logout reached', 'Workspace selection intercepted logout.');
session(['active_entity_id' => 1, 'active_plant_id' => 1]);
DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => 1]);
$response = (new SetEntityContext)->handle($logoutRequest, fn () => response('logout reached'));
checkWorkspace($response->getContent() === 'logout reached', 'Suspended workspace intercepted logout.');
DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => 0]);
session()->forget(['active_entity_id', 'active_plant_id']);
$user->is_otp_enabled = true;
session(['otp_pending' => true]);
$response = (new App\Http\Middleware\RequireOtpVerification)->handle($logoutRequest, fn () => response('logout reached'));
checkWorkspace($response->getContent() === 'logout reached', 'Pending OTP intercepted logout.');
$response = (new App\Http\Middleware\RequireOtpVerification)->handle($request, fn () => response('dashboard'));
checkWorkspace($response->isRedirect(route('otp.show')), 'OTP verification no longer protects other routes.');
$otpRequest = Request::create('https://modormc.com/verifyotp');
$otpRoute = new Illuminate\Routing\Route('GET', 'verifyotp', fn () => null);
$otpRoute->name('otp.show');
$otpRequest->setRouteResolver(fn () => $otpRoute);
$response = (new SetEntityContext)->handle($otpRequest, fn () => response('otp reached'));
checkWorkspace($response->getContent() === 'otp reached', 'Workspace selection intercepted OTP verification.');
$user->is_otp_enabled = false;
session()->forget('otp_pending');
DB::table('mm_plants')->where('id', 2)->delete();
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => 2]);
checkWorkspace(!$ctx->hasWorkspaces($user), 'Nonexistent specific plant was accepted.');
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => 1]);
DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => 1]);
DB::table('mm_plants')->where('id', 1)->update(['is_active' => -1]);
checkWorkspace(!$ctx->hasWorkspaces($user), 'Suspended organization must not count as a displayed workspace.');
$user->forceFill(['default_entity_id' => 1, 'default_plant_id' => 1]);
foreach ([['is_suspended' => 1, 'is_active' => 1], ['is_suspended' => 0, 'is_active' => -1], ['is_suspended' => 0, 'is_active' => 0]] as $state) {
    DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => $state['is_suspended']]);
    DB::table('mm_plants')->where('id', 1)->update(['is_active' => $state['is_active']]);
    session()->forget(['active_entity_id', 'active_plant_id']);
    checkWorkspace($ctx->validDefaultPlant($user) === null && $ctx->plantId() === null, 'Restricted default was restored.');
    $response = (new SetEntityContext)->handle($request, fn () => response('dashboard'));
    $selection = (new EntityContextController)->index();
    if ($state['is_suspended'] === 0 && $state['is_active'] === -1) {
        checkWorkspace($response->isRedirect(route('entity-context.index')), 'Visible restricted workspace must still require selection.');
        checkWorkspace($selection instanceof Inertia\Response, 'Visible restricted workspace must retain its restriction screen.');
    } else {
        checkWorkspace($response->getContent() === 'dashboard', 'Empty workspace list blocked the dashboard.');
        checkWorkspace($selection instanceof Illuminate\Http\RedirectResponse && $selection->getTargetUrl() === url('/settings/roles'), 'Empty workspace list did not skip to last visit.');
        checkWorkspace(!session('active_plant_id') && $ctx->plantId() === null, 'Hidden workspace retained plant access.');
    }
}
DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => 0]);
DB::table('mm_plants')->where('id', 1)->update(['is_active' => 1]);
checkWorkspace($ctx->validDefaultPlant($user)?->id === 1, 'Valid default was rejected.');
$user->default_entity_id = 99;
checkWorkspace($ctx->validDefaultPlant($user) === null, 'Mismatched default entity was accepted.');
$user->default_entity_id = 1;
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => 99]);
checkWorkspace($ctx->validDefaultPlant($user) === null, 'Revoked default assignment was restored.');
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => 1]);
DB::table('mm_entity_users')->where('id', 1)->update(['deleted_at' => '2026-09-30']);
checkWorkspace(!$ctx->hasWorkspaces($user), 'Deleted assignment was accepted.');

echo "Workspace login checks passed.\n";
