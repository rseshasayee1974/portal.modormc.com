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
            'platform admin' => ['PLATFORM_ADMIN', 'Platform Admin', false],
            'administrator' => ['ADMINISTRATOR', 'Administrator', false],
            'admin' => ['ADMIN', 'Admin', false],
            'ordinary role' => ['ACCOUNTANT', 'Accountant', false],
            'code overrides owner name' => ['ACCOUNTANT', 'Saas Owner', false],
            'code overrides super name' => ['PLATFORM_ADMIN', 'Super Admin', false],
            'legacy owner name' => [null, 'Saas Owner', true],
            'legacy super name' => [null, 'Super Admin', true],
            'legacy super administrator' => [null, 'Super Administrator', true],
            'legacy platform name' => [null, 'Platform Admin', false],
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
    public function test_only_two_roles_bypass_gates_modules_reports_and_mobile(?string $code, string $name, bool $allowed): void
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
        return [['PLATFORM_ADMIN', 'Platform Admin'], ['ADMINISTRATOR', 'Administrator']];
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
        foreach (['SAAS_OWNER' => true, 'SUPER_ADMIN' => true, 'PLATFORM_ADMIN' => false, 'ADMINISTRATOR' => false] as $code => $allowed) {
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
        $this->assertSame($allowed, $props['menus']['top_nav']->contains('id', 10));
        $this->assertFalse(isset($props['menus']['sidebar_nav'][10]));
    }

}
