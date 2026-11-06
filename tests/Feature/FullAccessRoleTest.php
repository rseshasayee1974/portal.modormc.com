<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 18:14:35 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Mobile\MobileAccess;
use App\Services\Reports\ReportPermissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FullAccessRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('mm_permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('guard_name');
            $table->string('module')->nullable(); $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); $table->timestamps();
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function roles(): array
    {
        return [
            'owner code' => ['SAAS_OWNER', 'Renamed owner', true],
            'super code' => ['SUPER_ADMIN', 'Renamed super', true],
            'platform admin' => ['PLATFORM_ADMIN', 'Platform Admin', true],
            'administrator' => ['ADMINISTRATOR', 'Administrator', false],
            'admin' => ['ADMIN', 'Admin', false],
            'ordinary role' => ['ACCOUNTANT', 'Accountant', false],
            'code overrides owner name' => ['ACCOUNTANT', 'Saas Owner', false],
            'code overrides super name' => ['PLATFORM_ADMIN', 'Super Admin', true],
            'legacy owner name' => [null, 'Saas Owner', true],
            'legacy super name' => [null, 'Super Admin', true],
            'legacy super administrator' => [null, 'Super Administrator', true],
            'legacy platform name' => [null, 'Platform Admin', true],
        ];
    }

    private function user(?string $code, string $name): User
    {
        $role = new Role(['code' => $code, 'name' => $name, 'guard_name' => 'web']);
        $role->setRelation('permissions', new Collection);
        $user = new User;
        $user->forceFill(['id' => 99]);
        $user->setRelation('roles', new Collection([$role]));
        $user->setRelation('permissions', new Collection);
        $this->actingAs($user);
        request()->setUserResolver(fn () => $user);
        return $user;
    }

    #[DataProvider('roles')]
    public function test_invoice_copy_selection_does_not_grant_full_access(?string $code, string $name, bool $fullAccess): void
    {
        $user = $this->user($code, $name);
        $expected = $fullAccess || in_array($code, ['ADMINISTRATOR', 'ADMIN'], true);
        $this->assertSame($expected, $user->canChooseInvoiceCopy());
        $this->assertSame($fullAccess, $user->hasFullAccess());
    }

    #[DataProvider('roles')]
    public function test_system_roles_bypass_gates_modules_reports_and_mobile(?string $code, string $name, bool $allowed): void
    {
        $user = $this->user($code, $name);
        Gate::define('LEDGER.CREATE', fn () => false);
        Gate::define('LEDGERS.CREATE', fn () => false);
        $this->assertSame($allowed, $user->hasFullAccess());
        $this->assertSame($allowed, $user->isSystemAdmin());
        $this->assertSame($allowed, Gate::allows('LEDGER.CREATE'));
        $this->assertSame($allowed, app(ReportPermissions::class)->allows('ledger', 'export'));
        $this->assertSame($allowed, MobileAccess::permits('LEDGER', 'CREATE'));

        $controller = new class {
            use AuthorizesModule;
            protected string $module = 'ledgers';
            public function check(): void { $this->authorizeModule('create'); }
        };
        try {
            $controller->check();
            $this->assertTrue($allowed, 'A role without permission bypassed module authorization.');
        } catch (HttpException $exception) {
            $this->assertFalse($allowed);
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public static function permissionBasedRoles(): array
    {
        return [['ADMINISTRATOR', 'Administrator'], ['ACCOUNTANT', 'Accountant']];
    }

    #[DataProvider('permissionBasedRoles')]
    public function test_other_roles_still_use_explicit_permissions(string $code, string $name): void
    {
        $user = $this->user($code, $name);
        $user->setRelation('permissions', new Collection([
            (new Permission(['name' => 'REPORT_LEDGER.VIEW', 'guard_name' => 'web']))->forceFill(['id' => 1]),
            (new Permission(['name' => 'REPORT_LEDGER.EXPORT', 'guard_name' => 'web']))->forceFill(['id' => 2]),
            (new Permission(['name' => 'LEDGER.CREATE', 'guard_name' => 'web']))->forceFill(['id' => 3]),
        ]));
        $this->assertFalse($user->hasFullAccess());
        $this->assertTrue(app(ReportPermissions::class)->allows('ledger', 'export'));
        $this->assertFalse(app(ReportPermissions::class)->allows('ledger', 'share'));
        $this->assertTrue(MobileAccess::permits('LEDGER', 'CREATE'));
        $this->assertFalse(MobileAccess::permits('LEDGER', 'DELETE'));
    }

    public function test_report_installation_does_not_automatically_grant_administrators_access(): void
    {
        Schema::create('mm_roles', function (Blueprint $table) {
            $table->id(); $table->string('code'); $table->softDeletes();
        });
        Schema::create('mm_role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id'); $table->unsignedBigInteger('permission_id');
            $table->unique(['role_id', 'permission_id']);
        });
        Schema::create('mm_model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('model_id'); $table->string('model_type'); $table->unsignedBigInteger('permission_id');
        });
        foreach (['SAAS_OWNER', 'SUPER_ADMIN', 'PLATFORM_ADMIN', 'ADMINISTRATOR'] as $id => $code) {
            \Illuminate\Support\Facades\DB::table('mm_roles')->insert(['id' => $id + 1, 'code' => $code]);
        }
        \App\Services\Reports\InstallReportPermissions::run();
        $grants = \Illuminate\Support\Facades\DB::table('mm_role_has_permissions');
        $this->assertGreaterThan(0, (clone $grants)->where('role_id', 1)->count());
        $this->assertGreaterThan(0, (clone $grants)->where('role_id', 2)->count());
        $this->assertSame(0, (clone $grants)->whereIn('role_id', [3, 4])->count());
    }

    public function test_full_access_covers_master_data_and_every_module_action(): void
    {
        $controller = new class {
            use AuthorizesModule;
            public function check(string $module, string $action): void { $this->authorizeModule($action, $module); }
        };
        foreach (['SAAS_OWNER', 'SUPER_ADMIN'] as $code) {
            $this->user($code, 'Renamed privileged role');
            foreach (['roles', 'menus', 'permissions', 'ledgers', 'opening_balance'] as $module) {
                foreach (['menu', 'create', 'edit', 'update', 'delete', 'export'] as $action) {
                    $controller->check($module, $action);
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_mobile_context_role_uses_the_same_full_access_rule(): void
    {
        $this->user('ACCOUNTANT', 'Accountant');
        request()->attributes->set('mobile_app_context', true);
        foreach (['SAAS_OWNER' => true, 'SUPER_ADMIN' => true, 'PLATFORM_ADMIN' => true, 'ADMINISTRATOR' => false] as $code => $allowed) {
            $role = new Role(['code' => $code, 'name' => 'Context role']);
            $role->setRelation('permissions', new Collection);
            request()->attributes->set('mobile_role', $role);
            $this->assertSame($allowed, MobileAccess::permits('QUOTATION', 'DELETE'));
            $this->assertSame($allowed, MobileAccess::isCommercialAdministrator());
        }
    }

    public static function reportMenuCases(): array
    {
        return [
            'lowercase permitted' => ['report', true],
            'live capitalized permitted' => ['Report', true],
            'uppercase permitted' => ['REPORT', true],
            'whitespace permitted' => [' Report ', true],
            'capitalized denied' => ['Report', false],
            'lowercase denied' => ['report', false],
        ];
    }

    #[DataProvider('reportMenuCases')]
    public function test_reports_menu_alias_case_respects_individual_report_access(string $alias, bool $allowed): void
    {
        Schema::create('mm_entity_users', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('entity_id'); $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('role_id'); $table->softDeletes();
        });
        Schema::create('mm_menus', function (Blueprint $table) {
            $table->id(); $table->integer('menutype'); $table->integer('parent_id')->default(0);
            $table->string('title'); $table->string('alias'); $table->string('link');
            $table->string('permission_name')->nullable(); $table->boolean('published')->default(true);
            $table->integer('ordering')->default(0); $table->softDeletes();
        });
        \Illuminate\Support\Facades\DB::table('mm_menus')->insert([
            ['id' => 10, 'menutype' => 1, 'parent_id' => 0, 'title' => 'Report', 'alias' => $alias, 'link' => 'reports/report', 'permission_name' => 'REPORT.VIEW'],
            ['id' => 11, 'menutype' => 2, 'parent_id' => 10, 'title' => 'Settings', 'alias' => 'restricted', 'link' => 'settings', 'permission_name' => 'SETTING.VIEW'],
        ]);
        $this->user('ADMINISTRATOR', 'Administrator');
        $reports = \Mockery::mock(ReportPermissions::class);
        $reports->shouldReceive('matrix')->once()->andReturn(['ledger' => ['view' => $allowed]]);
        $this->app->instance(ReportPermissions::class, $reports);
        request()->setLaravelSession(app('session.store'));
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertArrayHasKey('has_full_access', $props);
        $this->assertFalse($props['has_full_access']);
        $this->assertSame($allowed, $props['menus']['top_nav']->contains('id', 10));
        $this->assertFalse(isset($props['menus']['sidebar_nav'][10]));
    }


    public function test_administrator_menus_and_endpoints_follow_explicit_permissions_in_the_selected_context(): void
    {
        $db = \Illuminate\Support\Facades\DB::class;
        foreach ([
            'mm_roles' => 'id INTEGER PRIMARY KEY, code TEXT, name TEXT, guard_name TEXT, deleted_at TEXT',
            'mm_role_has_permissions' => 'role_id INTEGER, permission_id INTEGER',
            'mm_entity_users' => 'id INTEGER PRIMARY KEY, user_id INTEGER, entity_id INTEGER, plant_id INTEGER, role_id INTEGER, deleted_at TEXT',
            'mm_entities' => 'id INTEGER PRIMARY KEY, legal_name TEXT, is_suspended INTEGER, deleted_at TEXT',
            'mm_plants' => 'id INTEGER PRIMARY KEY, entity_id INTEGER, name TEXT, code TEXT, is_active INTEGER, deleted_at TEXT',
            'mm_custom_settings' => 'id INTEGER PRIMARY KEY, plant_id INTEGER, module_name TEXT, settings TEXT, deleted_at TEXT',
            'mm_menus' => 'id INTEGER PRIMARY KEY, menutype INTEGER, parent_id INTEGER, title TEXT, alias TEXT, link TEXT, permission_name TEXT, published INTEGER DEFAULT 1, ordering INTEGER DEFAULT 0, deleted_at TEXT',
        ] as $table => $columns) {
            $db::statement("CREATE TABLE {$table} ({$columns})");
        }
        $db::table('mm_roles')->insert(['id' => 4, 'code' => 'ADMINISTRATOR', 'name' => 'Administrator', 'guard_name' => 'web']);
        $db::table('mm_entities')->insert(['id' => 1, 'legal_name' => 'Assigned entity', 'is_suspended' => 0]);
        $db::table('mm_plants')->insert([
            ['id' => 1, 'entity_id' => 1, 'name' => 'Assigned plant', 'code' => 'A', 'is_active' => 1],
            ['id' => 2, 'entity_id' => 1, 'name' => 'Other plant', 'code' => 'B', 'is_active' => 1],
        ]);
        $db::table('mm_entity_users')->insert(['id' => 1, 'user_id' => 99, 'entity_id' => 1, 'plant_id' => 1, 'role_id' => 4]);
        $db::table('mm_menus')->insert([
            ['id' => 2, 'menutype' => 1, 'parent_id' => 0, 'title' => 'Master', 'alias' => 'master', 'link' => 'master', 'permission_name' => 'MASTER.ALL'],
            ['id' => 10, 'menutype' => 1, 'parent_id' => 0, 'title' => 'Report', 'alias' => 'Report', 'link' => 'reports/report', 'permission_name' => 'REPORT.VIEW'],
            ['id' => 11, 'menutype' => 1, 'parent_id' => 0, 'title' => 'Finance', 'alias' => 'finance', 'link' => 'finance', 'permission_name' => 'LEDGER.VIEW'],
            ['id' => 12, 'menutype' => 2, 'parent_id' => 11, 'title' => 'Ledger', 'alias' => 'ledger', 'link' => 'finance/ledger', 'permission_name' => 'LEDGER.VIEW'],
            ['id' => 13, 'menutype' => 2, 'parent_id' => 2, 'title' => 'Roles', 'alias' => 'roles', 'link' => 'settings/roles', 'permission_name' => 'ROLE.VIEW'],
        ]);
        $user = $this->user('ADMINISTRATOR', 'Administrator');
        session(['active_entity_id' => 1, 'active_plant_id' => 1]);
        request()->setLaravelSession(app('session.store'));
        $this->assertFalse($user->isSystemAdmin());
        $this->assertFalse(app(ReportPermissions::class)->allows('ledger', 'export'));
        $controller = new class {
            use AuthorizesModule;
            protected string $module = 'ledgers';
            public function check(): void { $this->authorizeModule('create'); }
        };
        $roleController = new class {
            use AuthorizesModule;
            protected string $module = 'roles';
            public function check(): void { $this->authorizeModule('menu'); }
        };
        // A global grant must not override the selected role's missing permission.
        Gate::define('ROLE.VIEW', fn () => true);
        Gate::define('LEDGER.CREATE', fn () => true);
        Gate::define('LEDGERS.CREATE', fn () => true);
        foreach ([$controller, $roleController] as $guarded) {
            try {
                $guarded->check();
                $this->fail('An unpermitted direct URL was authorized.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertFalse($props['has_full_access']);
        $this->assertSame('ADMINISTRATOR', $props['user_code']);
        foreach ([2, 10, 11] as $id) $this->assertFalse($props['menus']['top_nav']->contains('id', $id));
        $this->assertFalse(\App\Support\BatchNumberAccess::allows($user));

        foreach (['LEDGER.VIEW', 'LEDGER.CREATE', 'REPORT_LEDGER.VIEW', 'REPORT_LEDGER.EXPORT', 'ROLE.VIEW', 'BATCH.EDIT_BATCH_NO'] as $index => $name) {
            $db::table('mm_permissions')->insert(['id' => $index + 1, 'name' => $name, 'guard_name' => 'web']);
            $db::table('mm_role_has_permissions')->insert(['role_id' => 4, 'permission_id' => $index + 1]);
        }
        $controller->check();
        $roleController->check();
        $this->assertTrue(app(ReportPermissions::class)->allows('ledger', 'export'));
        $this->assertFalse(app(ReportPermissions::class)->allows('ledger', 'share'));
        $this->assertTrue(\App\Support\BatchNumberAccess::allows($user));
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertFalse($props['has_full_access']);
        foreach ([10, 11] as $id) $this->assertTrue($props['menus']['top_nav']->contains('id', $id));
        $this->assertFalse($props['menus']['top_nav']->contains('id', 2));
        $this->assertFalse($props['menus']['sidebar_nav']->has(2));

        // Child permissions alone must not bypass the Master menu grant.
        $db::table('mm_permissions')->insert(['id' => 7, 'name' => 'MASTER.ALL', 'guard_name' => 'web']);
        $db::table('mm_role_has_permissions')->insert(['role_id' => 4, 'permission_id' => 7]);
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertTrue($props['menus']['top_nav']->contains('id', 2));
        $this->assertSame('settings/roles', $props['menus']['top_nav']->firstWhere('id', 2)->link);
        $this->assertTrue($props['menus']['sidebar_nav'][2]->contains('id', 13));
        $this->assertTrue($props['menus']['sidebar_nav'][11]->contains('id', 12));

        $db::table('mm_role_has_permissions')->where('permission_id', 7)->delete();
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertFalse($props['menus']['top_nav']->contains('id', 2));
        $this->assertFalse($props['menus']['sidebar_nav']->has(2));

        $db::table('mm_role_has_permissions')->insert(['role_id' => 4, 'permission_id' => 7]);

        // Removing a module's VIEW grant hides both its child and its parent.
        $db::table('mm_role_has_permissions')->where('permission_id', 5)->delete();
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertFalse($props['menus']['top_nav']->contains('id', 2));
        try {
            $roleController->check();
            $this->fail('A revoked Master module permission still authorized the URL.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        // Simulate permissions on a different global role while changing plant.
        $globalPermissions = new Collection([
            (new Permission(['name' => 'LEDGER.VIEW', 'guard_name' => 'web']))->forceFill(['id' => 1]),
            (new Permission(['name' => 'LEDGER.CREATE', 'guard_name' => 'web']))->forceFill(['id' => 2]),
            (new Permission(['name' => 'BATCH.CREATE', 'guard_name' => 'web']))->forceFill(['id' => 6]),
        ]);
        $user->roles->first()->setRelation('permissions', $globalPermissions);
        session(['active_plant_id' => 2]);
        $this->assertFalse(\App\Support\BatchNumberAccess::allows($user));
        try {
            $controller->check();
            $this->fail('Administrator access leaked into an unassigned plant.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertFalse(app(ReportPermissions::class)->allows('ledger', 'export'));
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertFalse($props['has_full_access']);
        $this->assertFalse($props['menus']['top_nav']->contains('id', 10));
        $this->assertFalse($props['menus']['top_nav']->contains('id', 11));

        $db::table('mm_entity_users')->insert(['id' => 2, 'user_id' => 99, 'entity_id' => 1, 'plant_id' => null, 'role_id' => 4]);
        session()->forget('active_plant_id');
        $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
        $this->assertSame('ADMINISTRATOR', $props['user_code']);
        $this->assertTrue($props['menus']['top_nav']->contains('id', 11));
        $this->assertFalse($props['has_full_access']);

        foreach (['SAAS_OWNER', 'SUPER_ADMIN', 'PLATFORM_ADMIN'] as $code) {
            $this->user($code, 'System role');
            $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request());
            $this->assertTrue($props['has_full_access']);
            $this->assertTrue($props['menus']['top_nav']->contains('id', 10));
        }
    }

}
