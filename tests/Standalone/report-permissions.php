<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, (string) $e); exit(1); });

use App\Models\{User, Role, Permission};
use App\Services\Reports\{ReportPermissions, InstallReportPermissions, SalesRegisterService, PurchaseRegisterService, ReportServiceFactory, ExcelExportService};
use App\Http\Controllers\{ReportController, BulkDocumentReportController, InvoiceShareController};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache, Bus};
use Symfony\Component\HttpKernel\Exception\HttpException;

config(['database.default' => 'report_permissions_test', 'database.connections.report_permissions_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);
session(['active_plant_id' => 1]);

function checkAccess(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function denied(callable $operation, int $status = 403): void {
    try { $operation(); throw new RuntimeException('Unauthorized report action was accepted.'); }
    catch (HttpException $e) { checkAccess($e->getStatusCode() === $status, 'Wrong denial status: '.$e->getStatusCode()); }
}

foreach ([
    'mm_permissions' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, guard_name TEXT, module TEXT, description TEXT, is_system INTEGER, created_at TEXT, updated_at TEXT',
    'mm_roles' => 'id INTEGER PRIMARY KEY, name TEXT, code TEXT, guard_name TEXT, deleted_at TEXT',
    'mm_role_has_permissions' => 'permission_id INTEGER, role_id INTEGER, UNIQUE(permission_id, role_id)',
    'mm_model_has_permissions' => 'permission_id INTEGER, model_id INTEGER, model_type TEXT, UNIQUE(permission_id, model_id, model_type)',
    'mm_model_has_roles' => 'role_id INTEGER, model_id INTEGER, model_type TEXT',
    'mm_entity_users' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, plant_id INTEGER, user_id INTEGER, role_id INTEGER, deleted_at TEXT',
    'mm_menus' => 'id INTEGER PRIMARY KEY, link TEXT, permission_name TEXT',
    'mm_users' => 'id INTEGER PRIMARY KEY, username TEXT, deleted_at TEXT',
    'mm_plants' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, deleted_at TEXT',
    'mm_report_schedules' => 'id INTEGER PRIMARY KEY, plant_id INTEGER, report_type TEXT, report_params TEXT, deleted_at TEXT',
] as $table => $columns) DB::statement("CREATE TABLE $table ($columns)");
foreach (['REPORT.VIEW', 'REPORT.EXPORT', 'INVOICE.VIEW'] as $name) DB::table('mm_permissions')->insert(['name' => $name, 'guard_name' => 'web']);
DB::table('mm_roles')->insert([
    ['id' => 1, 'name' => 'Reader', 'code' => 'READER', 'guard_name' => 'web'],
    ['id' => 2, 'name' => 'Exporter', 'code' => 'EXPORTER', 'guard_name' => 'web'],
    ['id' => 3, 'name' => 'Super Admin', 'code' => 'SUPER_ADMIN', 'guard_name' => 'web'],
]);
DB::table('mm_role_has_permissions')->insert([['role_id' => 1, 'permission_id' => 1], ['role_id' => 2, 'permission_id' => 1], ['role_id' => 2, 'permission_id' => 2]]);
DB::table('mm_model_has_permissions')->insert(['model_type' => User::class, 'model_id' => 77, 'permission_id' => 1]);
DB::table('mm_menus')->insert([
    ['id' => 1, 'link' => 'reports/report?type=sales_register', 'permission_name' => 'REPORT.VIEW'],
    ['id' => 2, 'link' => 'reports/report?type=sales_register&register_view=detail', 'permission_name' => 'REPORT.VIEW'],
    ['id' => 3, 'link' => 'reports/report?type=overall', 'permission_name' => 'ACCOUNT.VIEW'],
    ['id' => 4, 'link' => 'finance/invoices', 'permission_name' => 'INVOICE.VIEW'],
]);
$migration = require __DIR__.'/../../database/migrations/2026_09_23_140000_add_individual_report_permissions.php';
$migration->up();
$migration->up();
$reportCount = count(ReportPermissions::REPORTS);
checkAccess(DB::table('mm_permissions')->count() === $reportCount * 4 + 3, 'Migration missing/duplicating report permissions.');
checkAccess(DB::table('mm_role_has_permissions')->where('role_id', 1)->count() === $reportCount + 1, 'Reader inherited export or lost view.');
checkAccess(DB::table('mm_role_has_permissions')->where('role_id', 2)->count() === $reportCount * 3 + 2, 'Legacy export mapping wrong.');
checkAccess(DB::table('mm_model_has_permissions')->where('model_id', 77)->count() === $reportCount + 1, 'Direct permissions lost.');
checkAccess(DB::table('mm_menus')->where('id', 2)->value('permission_name') === 'REPORT_DETAILED_SALES_REGISTER.VIEW', 'Detail menu permission wrong.');
checkAccess(DB::table('mm_menus')->where('id', 4)->value('permission_name') === 'INVOICE.VIEW', 'Unrelated menu modified.');
$roleController = new class extends App\Http\Controllers\RoleController {
    protected function authorizeModule(string $action, ?string $module = null): void {}
};
$rolePage = $roleController->index(Request::create('/settings/roles'));
$roleProps = (new ReflectionProperty(Inertia\Response::class, 'props'))->getValue($rolePage);
$reportGroups = array_filter(array_keys($roleProps['groupedPermissions']), fn ($label) => str_starts_with($label, 'Report: '));
checkAccess(count($reportGroups) === $reportCount && isset($roleProps['groupedPermissions']['REPORT']), 'Role matrix missing report rows or REPORT permissions.');
$ledgerPermission = DB::table('mm_permissions')->where('name', 'REPORT_LEDGER.VIEW')->value('id');
DB::table('mm_role_has_permissions')->where('role_id', 1)->where('permission_id', $ledgerPermission)->delete();
InstallReportPermissions::run();
checkAccess(!DB::table('mm_role_has_permissions')->where('role_id', 1)->where('permission_id', $ledgerPermission)->exists(), 'Rerun re-granted a revoked permission.');

$makeUser = function (array $names = [], string $roleName = 'Operator'): User {
    $user = (new User)->forceFill(['id' => 100]);
    $role = (new Role)->forceFill(['id' => 10, 'name' => $roleName, 'guard_name' => 'web']);
    $role->setRelation('permissions', new Collection);
    $user->setRelation('roles', new Collection([$role]));
    $user->setRelation('permissions', Permission::whereIn('name', $names)->get());
    return $user;
};
$access = app(ReportPermissions::class);
foreach (ReportPermissions::REPORTS as $id => $label) {
    $params = ['register_view' => 'summary'];
    $user = $makeUser([ReportPermissions::name($id, 'VIEW')]);
    checkAccess($access->allows($id, 'view', $params, $user), "$id view denied.");
    foreach (['export', 'share', 'schedule'] as $action) {
        checkAccess(!$access->allows($id, $action, $params, $user), "$id view granted $action.");
        $allowed = $makeUser([ReportPermissions::name($id, 'VIEW'), ReportPermissions::name($id, $action)]);
        checkAccess($access->allows($id, $action, $params, $allowed), "$id $action denied.");
    }
    $other = $id === 'ledger' ? 'purchase_register' : 'ledger';
    checkAccess(!$access->allows($other, 'view', [], $user), "$id granted another report.");
}
checkAccess(!$access->allows('ledger', 'view', [], $makeUser(['REPORT.VIEW', 'REPORT.EXPORT'])), 'Blanket permission bypassed individual permissions.');
checkAccess(!$access->allows('sales_register', 'view', [], $makeUser(['REPORT_SALES_REGISTER.VIEW'])), 'Omitted layout bypassed detailed permission.');
checkAccess($access->allows('sales_register', 'view', [], $makeUser(['REPORT_DETAILED_SALES_REGISTER.VIEW'])), 'Detailed permission not resolved.');
checkAccess($access->allows('deleted', 'view', [], $makeUser(['REPORT_DELETED_REPORT.VIEW'])), 'Deleted alias mapped incorrectly.');
checkAccess($access->allows('ledger', 'export', [], $makeUser([], 'Super Admin')), 'System administrator bypass lost.');

// An active tenant role overrides unrelated global role grants; direct grants supplement it.
DB::table('mm_entity_users')->insert(['id' => 1, 'entity_id' => 1, 'plant_id' => 1, 'user_id' => 100, 'role_id' => 1]);
session(['active_entity_id' => 1]);
$global = $makeUser();
$global->roles[0]->setRelation('permissions', Permission::where('name', 'REPORT_LEDGER.VIEW')->get());
checkAccess(!$access->allows('ledger', 'view', [], $global), 'Global role overrode selected entity role.');
checkAccess($access->allows('ledger', 'view', [], $makeUser(['REPORT_LEDGER.VIEW'])), 'Direct permission did not supplement entity role.');
session(['active_plant_id' => 2]);
checkAccess(!$access->allows('ledger', 'view', [], $makeUser(['REPORT_LEDGER.VIEW'])), 'Unassigned plant inherited a direct report grant.');
session(['active_plant_id' => 1]);
session()->forget('active_entity_id');

// Exercise actual HTTP entry points: failures must remain 403, including the generic catch block.
$controller = app(ReportController::class);
auth()->setUser($makeUser(['REPORT_SALES_REGISTER.VIEW']));
$dates = ['from_date' => '2026-09-01', 'to_date' => '2026-09-23'];
denied(fn () => $controller->index(Request::create('/', 'GET', ['type' => 'deleted_report'])));
denied(fn () => $controller->generate(Request::create('/', 'GET', ['type' => 'deleted']), app(ReportServiceFactory::class), app(ExcelExportService::class)));
denied(fn () => $controller->salesRegister(Request::create('/', 'GET', $dates), app(SalesRegisterService::class)));
denied(fn () => $controller->salesRegister(Request::create('/', 'GET', $dates + ['register_view' => 'summary', 'export' => 'pdf']), app(SalesRegisterService::class)));
denied(fn () => $controller->purchaseRegister(Request::create('/', 'GET', $dates), app(PurchaseRegisterService::class)));
denied(fn () => $controller->machineSummary(Request::create('/', 'GET', $dates), app(App\Services\Reports\MachineReportService::class)));
denied(fn () => $controller->vehiclePL(Request::create('/', 'GET', $dates), app(App\Services\Reports\MachineReportService::class)));
denied(fn () => app(BulkDocumentReportController::class)->index());
denied(fn () => app(BulkDocumentReportController::class)->documents(Request::create('/', 'POST'), app(App\Services\Reports\BulkDocumentQuery::class), app(App\Services\BulkInvoicePdfService::class)));
denied(fn () => app(BulkDocumentReportController::class)->exportZip(Request::create('/', 'POST'), app(App\Services\Reports\BulkDocumentQuery::class)));
denied(fn () => $controller->storeSchedule(Request::create('/', 'POST', ['report_type' => 'sales_register', 'report_params' => ['register_view' => 'summary']])));
denied(fn () => app(InvoiceShareController::class)->generateLink(Request::create('/', 'POST', ['document_type' => 'report', 'expiry' => '7', 'report_params' => ['type' => 'sales_register', 'register_view' => 'summary']])));

$viewer = $makeUser(['REPORT_LEDGER.VIEW', 'REPORT_LEDGER.EXPORT']);
auth()->setUser($viewer);
$access->rememberExport('test-protected-export', 'ledger', []);
Cache::put('test-protected-export', ['status' => 'completed', 'filename' => 'test-permissions-'.bin2hex(random_bytes(4)).'.xlsx'], 60);
checkAccess($controller->getExportStatus('test-protected-export')->getData(true)['status'] === 'completed', 'Owner cannot poll export.');
$otherUser = $makeUser(['REPORT_LEDGER.VIEW', 'REPORT_LEDGER.EXPORT'])->forceFill(['id' => 101]);
auth()->setUser($otherUser);
denied(fn () => $controller->getExportStatus('test-protected-export'));
auth()->setUser($viewer);
session(['active_plant_id' => 2]);
denied(fn () => $controller->getExportStatus('test-protected-export'));
session(['active_plant_id' => 1]);
$path = storage_path('app/private/reports/'.Cache::get('test-protected-export')['filename']);
Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
file_put_contents($path, 'test export');
try {
    checkAccess($controller->downloadExport('test-protected-export')->getFile()->getRealPath() === realpath($path), 'Private download failed.');
    auth()->setUser($makeUser(['REPORT_LEDGER.VIEW']));
    denied(fn () => $controller->downloadExport('test-protected-export'));
} finally { unlink($path); }
auth()->setUser($viewer);
Bus::fake();
config(['reports.async_exports' => false]);
App\Jobs\QueueReportExportJob::dispatchExport('ledger', ['plant_id' => 1], 'test-job-access');
checkAccess(Cache::get('test-job-access:access')['user_id'] === 100, 'Job lost owner metadata.');

// Schedule lists expose only reports that the user can schedule.
DB::table('mm_report_schedules')->insert([
    ['id' => 1, 'plant_id' => 1, 'report_type' => 'ledger', 'report_params' => '{}'],
    ['id' => 2, 'plant_id' => 1, 'report_type' => 'purchase_register', 'report_params' => '{}'],
    ['id' => 3, 'plant_id' => 2, 'report_type' => 'ledger', 'report_params' => '{}'],
]);
auth()->setUser($makeUser(['REPORT_LEDGER.VIEW', 'REPORT_LEDGER.SCHEDULE']));
checkAccess(array_column($controller->listSchedules(Request::create('/'))->getData(true), 'id') === [1], 'Schedule list leaked a denied report or plant.');

// Existing public report links stop working if their creator's permission is removed.
DB::table('mm_users')->insert(['id' => 200, 'username' => 'Share owner']);
DB::table('mm_plants')->insert(['id' => 1, 'entity_id' => 1]);
DB::table('mm_entity_users')->insert(['id' => 2, 'entity_id' => 1, 'plant_id' => 1, 'user_id' => 200]);
$shareIds = DB::table('mm_permissions')->whereIn('name', ['REPORT_LEDGER.VIEW', 'REPORT_LEDGER.SHARE'])->pluck('id');
foreach ($shareIds as $id) DB::table('mm_model_has_permissions')->insert(['model_type' => User::class, 'model_id' => 200, 'permission_id' => $id]);
$link = (new App\Models\PublicDocumentLink)->forceFill(['is_active' => true, 'document_type' => 'report',
    'plant_id' => 1, 'created_by' => 200, 'document_params' => ['type' => 'ledger']]);
$validateLink = new ReflectionMethod(InvoiceShareController::class, 'validateLink');
checkAccess($validateLink->invoke(app(InvoiceShareController::class), $link), 'Permitted public report link rejected.');
DB::table('mm_model_has_permissions')->where('model_id', 200)->delete();
checkAccess(!$validateLink->invoke(app(InvoiceShareController::class), $link), 'Revoked public report link still permitted.');

$migration->down();
checkAccess(DB::table('mm_permissions')->count() === 3, 'Rollback removed unrelated permissions.');
echo "All $reportCount report permissions, role/direct grant migration, tenant scope, endpoint denials and private export checks passed.\n";
