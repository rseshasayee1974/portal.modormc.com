<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 13:45:17 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\BatchingDashboardController;
use App\Http\Controllers\ERPDashboardController;
use App\Http\Controllers\InventoryDashboardController;
use App\Models\Role;
use App\Models\User;
use App\Repositories\DashboardRepository;
use App\Services\DashboardPlantAccess;
use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DashboardPlantAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'dashboard_access_test', 'database.connections.dashboard_access_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::create('mm_entities', function (Blueprint $table) {
            $table->id(); $table->boolean('is_suspended')->default(false); $table->softDeletes();
        });
        Schema::create('mm_plants', function (Blueprint $table) {
            $table->id(); $table->integer('entity_id'); $table->string('name');
            $table->boolean('is_active')->default(true); $table->softDeletes();
        });
        Schema::create('mm_entity_users', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->integer('entity_id');
            $table->integer('plant_id')->nullable(); $table->softDeletes();
        });
        Schema::create('mm_dashboard_alerts', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('message');
            $table->timestamps(); $table->softDeletes();
        });
        DB::table('mm_entities')->insert([
            ['id' => 1, 'is_suspended' => false], ['id' => 2, 'is_suspended' => false], ['id' => 3, 'is_suspended' => true],
        ]);
        foreach ([1 => 1, 2 => 1, 3 => 2, 4 => 1, 5 => 1, 6 => 3] as $plantId => $entityId) {
            DB::table('mm_plants')->insert([
                'id' => $plantId, 'entity_id' => $entityId, 'name' => 'Plant ' . $plantId,
                'is_active' => $plantId !== 4, 'deleted_at' => $plantId === 5 ? now() : null,
            ]);
            DB::table('mm_dashboard_alerts')->insert(['plant_id' => $plantId, 'message' => 'Alert ' . $plantId, 'created_at' => now()]);
        }
        DB::table('mm_entity_users')->insert([
            ['user_id' => 99, 'entity_id' => 1, 'plant_id' => 1, 'deleted_at' => null],
            ['user_id' => 99, 'entity_id' => 1, 'plant_id' => 2, 'deleted_at' => null],
            ['user_id' => 99, 'entity_id' => 2, 'plant_id' => 3, 'deleted_at' => now()],
            ['user_id' => 99, 'entity_id' => 3, 'plant_id' => 6, 'deleted_at' => null],
        ]);
    }

    private function user(?string $code = 'ADMINISTRATOR', string $name = 'Administrator'): User
    {
        $user = new User;
        $user->forceFill(['id' => 99, 'default_plant_id' => 1]);
        $user->setRelation('roles', new Collection([new Role(['code' => $code, 'name' => $name, 'guard_name' => 'web'])]));
        $this->actingAs($user);

        return $user;
    }

    private function request(User $user, array $parameters = []): Request
    {
        $request = Request::create('/dashboard', 'GET', $parameters);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        $request->headers->set('X-Inertia', 'true');
        $this->app->instance('request', $request);

        return $request;
    }

    #[DataProvider('dashboardRoles')]
    public function test_only_owner_and_superadmin_get_the_full_plant_list(?string $code, string $name, array $ids): void
    {
        $user = $this->user($code, $name);
        $this->assertSame($ids, app(DashboardPlantAccess::class)->query($user)->orderBy('id')->pluck('id')->all());
        $this->assertSame($ids, (new DashboardRepository)->getPlants()->pluck('id')->all());
    }

    public static function dashboardRoles(): array
    {
        return [
            'superadmin' => ['SUPER_ADMIN', 'Super Admin', [1, 2, 3, 6]],
            'owner' => ['SAAS_OWNER', 'Saas Owner', [1, 2, 3, 6]],
            'administrator' => ['ADMINISTRATOR', 'Administrator', [1, 2]],
            'platform admin is not an owner' => ['PLATFORM_ADMIN', 'Platform Admin', [1, 2]],
            'accountant' => ['ACCOUNTANT', 'Accountant', [1, 2]],
            'legacy superadmin' => [null, 'Super Administrator', [1, 2, 3, 6]],
            'legacy owner' => [null, 'Saas Owner', [1, 2, 3, 6]],
            'role code takes precedence' => ['ADMINISTRATOR', 'Saas Owner', [1, 2]],
        ];
    }

    public function test_dashboard_dropdown_does_not_reuse_the_global_plant_cache(): void
    {
        $request = $this->request($this->user());
        session(['active_plant_id' => 1]);
        Cache::put('plants', [['id' => 3, 'name' => 'Unassigned plant']], 60);
        $response = (new BatchingDashboardController)->index($request)->toResponse($request);
        $props = json_decode($response->getContent(), true)['props'];
        $this->assertSame([1, 2], array_column($props['plants'], 'id'));
        $this->assertSame(1, $props['activePlantId']);

        Cache::put('inventory.dashboard.data.1', ['metrics' => []], 60);
        $response = (new InventoryDashboardController)->index($request)->toResponse($request);
        $this->assertSame([1, 2], array_column(json_decode($response->getContent(), true)['props']['plants'], 'id'));
    }

    public function test_assigned_plant_selection_is_honored_without_changing_workspace(): void
    {
        $request = $this->request($this->user(), ['plant_id' => 2]);
        session(['active_plant_id' => 1]);
        $response = (new BatchingDashboardController)->index($request)->toResponse($request);
        $this->assertSame(2, json_decode($response->getContent(), true)['props']['activePlantId']);
        $this->assertSame(1, session('active_plant_id'));
    }

    #[DataProvider('dashboardControllers')]
    public function test_dashboard_requests_for_unassigned_plants_are_rejected(string $controller): void
    {
        $request = $this->request($this->user(), ['plant_id' => 3]);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You do not have access to this plant.');
        (new $controller)->index($request);
    }

    public static function dashboardControllers(): array
    {
        return [[BatchingDashboardController::class], [InventoryDashboardController::class], [ERPDashboardController::class]];
    }

    public function test_entity_wide_assignment_stays_within_its_entity(): void
    {
        $user = $this->user();
        DB::table('mm_entity_users')->where('user_id', 99)->delete();
        DB::table('mm_entity_users')->insert(['user_id' => 99, 'entity_id' => 1, 'plant_id' => null]);
        $this->assertSame([1, 2], app(DashboardPlantAccess::class)->query($user)->orderBy('id')->pluck('id')->all());
    }

    public function test_stale_context_and_missing_assignments_do_not_expose_other_plants(): void
    {
        $request = $this->request($this->user());
        session(['active_plant_id' => 3]);
        $this->assertSame(1, app(DashboardPlantAccess::class)->resolve($request));
        DB::table('mm_entity_users')->where('user_id', 99)->delete();
        $this->assertCount(0, (new DashboardRepository)->getPlants());
        $this->expectException(HttpException::class);
        app(DashboardPlantAccess::class)->resolve($request);
    }

    public function test_dashboard_alerts_and_tampered_filters_are_scoped_to_assigned_plants(): void
    {
        $this->user();
        $repository = new DashboardRepository;
        $this->assertSame([1, 2], $repository->getAlerts([])->pluck('plant_id')->sort()->values()->all());
        $this->assertCount(0, $repository->getAlerts(['plant_id' => 3]));
        $this->assertSame([2], $repository->getAlerts(['plant_id' => 2])->pluck('plant_id')->all());
    }

    public function test_anonymous_users_have_no_dashboard_plants(): void
    {
        $this->assertCount(0, (new DashboardRepository)->getPlants());
    }

    public function test_dashboard_cache_cannot_retain_access_after_a_plant_assignment_is_removed(): void
    {
        $this->user();
        Schema::create('mm_mix_designs', function (Blueprint $table) {
            $table->id(); $table->string('design_name');
        });
        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->integer('mixdesign_id'); $table->decimal('net_weight');
        });
        DB::table('mm_mix_designs')->insert(['id' => 1, 'design_name' => 'M15']);
        DB::table('mm_dispatches')->insert([
            ['plant_id' => 1, 'mixdesign_id' => 1, 'net_weight' => 10],
            ['plant_id' => 2, 'mixdesign_id' => 1, 'net_weight' => 20],
            ['plant_id' => 3, 'mixdesign_id' => 1, 'net_weight' => 1000],
        ]);
        Cache::put('dashboard.top.products.99.' . md5(json_encode([])), [['tonnage' => 1030]], 300);
        $service = new DashboardService(new DashboardRepository);
        $this->assertEquals(30, $service->getTopProducts([])->first()->tonnage);
        DB::table('mm_entity_users')->where('user_id', 99)->where('plant_id', 2)->delete();
        $this->assertEquals(10, $service->getTopProducts([])->first()->tonnage);
        $this->assertCount(0, $service->getTopProducts(['plant_id' => 3]));
    }
}
