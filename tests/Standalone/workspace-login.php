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
], 'cache.default' => 'array', 'permission.cache.store' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);
app(Spatie\Permission\PermissionRegistrar::class)->initializeCache();
app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

function checkWorkspace(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    'mm_entities' => 'id INTEGER PRIMARY KEY, is_suspended INTEGER, deleted_at TEXT',
    'mm_plants' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, is_active INTEGER, deleted_at TEXT',
    'mm_entity_users' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, plant_id INTEGER, user_id INTEGER, role_id INTEGER, deleted_at TEXT',
    'mm_roles' => 'id INTEGER PRIMARY KEY, name TEXT, code TEXT, guard_name TEXT, deleted_at TEXT',
    'mm_permissions' => 'id INTEGER PRIMARY KEY, name TEXT, guard_name TEXT, deleted_at TEXT',
    'mm_role_has_permissions' => 'role_id INTEGER, permission_id INTEGER',
    'mm_model_has_permissions' => 'permission_id INTEGER, model_id INTEGER, model_type TEXT',
    'mm_model_has_roles' => 'role_id INTEGER, model_id INTEGER, model_type TEXT',
    'mm_users' => 'id INTEGER PRIMARY KEY, username TEXT, email TEXT, default_entity_id INTEGER, default_plant_id INTEGER, deleted_at TEXT',
    'mm_menus' => 'id INTEGER PRIMARY KEY, menutype INTEGER, title TEXT, alias TEXT, link TEXT, permission_name TEXT, parent_id INTEGER, ordering INTEGER, published INTEGER, deleted_at TEXT',
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
foreach (['index', 'analytics'] as $action) {
    foreach ([[], ['refresh' => 1, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']] as $filters) {
        $dashboardRequest = Request::create('/dashboard', 'GET', $filters);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = (new ERPDashboardController)->{$action}($dashboardRequest);
        $props = (new ReflectionProperty(Inertia\Response::class, 'props'))->getValue($page);
        checkWorkspace($props['patrons']->isEmpty() && $props['initialData']['metrics'] === [], 'Dashboard without a workspace must render empty data.');
        checkWorkspace(DB::getQueryLog() === [], 'Dashboard without a workspace queried tenant data.');
        DB::disableQueryLog();
    }
}

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

DB::table('mm_roles')->insert([
    ['id' => 10, 'name' => 'Operations Manager', 'code' => 'OPERATIONS_MANAGER', 'guard_name' => 'web'],
    ['id' => 11, 'name' => 'Sales Manager', 'code' => 'SALES_MANAGER', 'guard_name' => 'web'],
]);
DB::table('mm_permissions')->insert([
    ['id' => 1, 'name' => 'DISPATCH.VIEW', 'guard_name' => 'web'],
    ['id' => 2, 'name' => 'SALES_ORDER.VIEW', 'guard_name' => 'web'],
]);
DB::table('mm_role_has_permissions')->insert([['role_id' => 10, 'permission_id' => 1], ['role_id' => 11, 'permission_id' => 2]]);
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => null, 'role_id' => 10, 'deleted_at' => null]);
session(['active_entity_id' => 1, 'active_plant_id' => 1]);
(new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace($user->hasRole('Operations Manager'), 'Organization-wide manager role was lost.');
checkWorkspace($user->hasPermissionTo('DISPATCH.VIEW'), 'Organization-wide manager permission was lost.');
checkWorkspace(!$user->hasPermissionTo('SALES_ORDER.VIEW'), 'Manager gained unrelated permissions.');
DB::table('mm_entity_users')->insert(['id' => 2, 'user_id' => 77, 'entity_id' => 1, 'plant_id' => 1, 'role_id' => 11]);
App\Models\EntityUser::clearContextCache(77);
(new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace($user->hasRole('Sales Manager') && !$user->hasPermissionTo('DISPATCH.VIEW'), 'Specific plant role did not override organization role.');
checkWorkspace(App\Models\EntityUser::forContext(77, 1, 2)?->role_id === 10, 'Other plant inherited the specific plant role.');
checkWorkspace(App\Models\EntityUser::forContext(77, 99, 1) === null, 'Role crossed organization boundary.');
DB::table('mm_entity_users')->where('id', 2)->update(['deleted_at' => '2026-09-30']);
App\Models\EntityUser::clearContextCache(77);
(new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace($user->hasRole('Operations Manager'), 'Deleted plant assignment prevented organization fallback.');
DB::table('mm_roles')->where('id', 10)->update(['deleted_at' => '2026-09-30']);
checkWorkspace(App\Models\EntityUser::forContext(77, 1, 1)?->role === null, 'Deleted role retained permissions.');
DB::table('mm_entity_users')->where('user_id', 77)->update(['deleted_at' => '2026-09-30']);
DB::table('mm_model_has_roles')->insert(['role_id' => 11, 'model_id' => 77, 'model_type' => $user->getMorphClass()]);
(new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace(!session('active_plant_id') && $user->hasRole('Sales Manager') && $user->hasPermissionTo('SALES_ORDER.VIEW'), 'Account-level manager grants were cleared without a workspace.');
DB::table('mm_permissions')->insert(['id' => 3, 'name' => 'ADDRESS_TYPE.VIEW', 'guard_name' => 'web']);
DB::table('mm_role_has_permissions')->insert(['role_id' => 11, 'permission_id' => 3]);
$user->unsetRelation('roles');
DB::table('mm_menus')->insert([
    ['id' => 2, 'menutype' => 1, 'title' => 'Master', 'alias' => 'master', 'link' => 'master', 'permission_name' => 'MASTER.VIEW', 'parent_id' => 0, 'ordering' => 1, 'published' => 1],
    ['id' => 3, 'menutype' => 2, 'title' => 'Address types', 'alias' => 'addresstypes', 'link' => 'master/addresstypes', 'permission_name' => 'ADDRESS_TYPE.VIEW', 'parent_id' => 2, 'ordering' => 1, 'published' => 1],
    ['id' => 4, 'menutype' => 2, 'title' => 'Roles', 'alias' => 'roles', 'link' => 'settings/roles', 'permission_name' => 'ROLE.VIEW', 'parent_id' => 2, 'ordering' => 2, 'published' => 1],
]);
$shared = (new App\Http\Middleware\HandleInertiaRequests)->share($request);
checkWorkspace($shared['user_role'] === 'Sales Manager' && $shared['user_permissions']->contains('ADDRESS_TYPE.VIEW'), 'Manager grants were missing from shared page props.');
checkWorkspace($shared['menus']['top_nav']->contains('id', 2), 'Master parent was hidden despite an authorized child.');
checkWorkspace($shared['menus']['sidebar_nav'][2]->contains('id', 3) && !$shared['menus']['sidebar_nav'][2]->contains('id', 4), 'Master menus did not follow manager grants.');
DB::table('mm_roles')->insert(['id' => 12, 'name' => 'Administrator', 'code' => 'ADMINISTRATOR', 'guard_name' => 'web']);
DB::table('mm_permissions')->insert(['id' => 4, 'name' => 'ROLE.VIEW', 'guard_name' => 'web']);
DB::table('mm_role_has_permissions')->insert([['role_id' => 12, 'permission_id' => 3], ['role_id' => 12, 'permission_id' => 4]]);
app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
DB::table('mm_model_has_roles')->where('model_id', 77)->update(['role_id' => 12]);
DB::table('mm_entity_users')->where('id', 1)->update(['plant_id' => null, 'role_id' => null, 'deleted_at' => null]);
App\Models\EntityUser::clearContextCache(77);
session(['active_entity_id' => 1, 'active_plant_id' => 1]);
(new SetEntityContext)->handle($request, fn () => response('dashboard'));
checkWorkspace($user->hasRole('Administrator') && $user->hasPermissionTo('ROLE.VIEW'), 'Account Administrator lost grants when workspace had no role override.');
checkWorkspace(!$user->hasPermissionTo('DISPATCH.VIEW'), 'Administrator gained an unassigned permission.');
session()->forget(['active_entity_id', 'active_plant_id']);
$shared = (new App\Http\Middleware\HandleInertiaRequests)->share($request);
checkWorkspace($shared['user_role'] === 'Administrator' && $shared['menus']['sidebar_nav'][2]->contains('id', 4), 'Administrator role grants were missing from page menus.');
DB::table('mm_model_has_roles')->where('model_id', 77)->delete();
$user->unsetRelation('roles');
$shared = (new App\Http\Middleware\HandleInertiaRequests)->share($request);
checkWorkspace($shared['user_permissions']->isEmpty() && !$shared['menus']['top_nav']->contains('id', 2), 'Account without grants gained restricted menus.');
DB::table('mm_users')->insert(['id' => 88, 'username' => 'administrator-fixture', 'email' => 'administrator@example.test']);
DB::table('mm_model_has_roles')->insert(['role_id' => 12, 'model_id' => 88, 'model_type' => User::class]);
DB::flushQueryLog();
DB::enableQueryLog();
$auditStatus = Illuminate\Support\Facades\Artisan::call('access:diagnose', ['account' => 'administrator-fixture']);
$auditOutput = Illuminate\Support\Facades\Artisan::output();
checkWorkspace($auditStatus === 0 && str_contains($auditOutput, 'Administrator') && str_contains($auditOutput, 'Read-only audit completed'), 'Production access diagnostic did not report the account role.');
foreach (DB::getQueryLog() as $query) {
    checkWorkspace(!preg_match('/^\s*(insert|update|delete|create|alter|drop)\b/i', $query['query']), 'Read-only diagnostic modified the database.');
}
DB::disableQueryLog();

echo "Workspace login checks passed.\n";
