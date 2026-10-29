<?php

/*
Author: ragul-onemodo
Created: 2026-10-25 19:54:08 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\ConcreteBatchingScheduleController;
use App\Models\User;
use App\Services\ProductionReadCache;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductionReadCacheTest extends TestCase
{
    private ProductionReadCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['production_cache.store' => 'production_test', 'production_cache.enabled' => true,
            'cache.stores.production_test' => ['driver' => 'array', 'serialize' => true]]);
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, plant_id INTEGER, status INTEGER)');
        DB::statement('CREATE TABLE mm_dispatches (id INTEGER PRIMARY KEY, plant_id INTEGER, status INTEGER)');
        DB::statement('CREATE TABLE unrelated (id INTEGER PRIMARY KEY)');
        DB::table('mm_batches')->insert(['id' => 1, 'plant_id' => 1, 'status' => 1]);
        $this->cache = app(ProductionReadCache::class);
        session(['active_plant_id' => 1, 'active_entity_id' => 1]);
        auth()->setUser((new User)->forceFill(['id' => 1]));
    }

    private function cachedStatus(): int
    {
        return $this->cache->remember('batch-grid', 1, [], fn () => (int) DB::table('mm_batches')->value('status'));
    }

    public function test_hits_are_isolated_by_plant_user_entity_filters_view_and_role(): void
    {
        $calls = 0;
        $load = function () use (&$calls) {
            return ++$calls;
        };
        $read = fn ($view = 'batch-grid', $plant = 1, $filters = []) => $this->cache->remember($view, $plant, $filters, $load);
        $this->assertSame(1, $read());
        $this->assertSame(1, $read());
        $this->assertSame(2, $read(plant: 2));
        $this->assertSame(3, $read(filters: ['status' => 'completed']));
        $this->assertSame(4, $read(view: 'dispatch-grid'));
        session(['active_entity_id' => 2]);
        $this->assertSame(5, $read());
        session(['active_role_id' => 7]);
        $this->assertSame(6, $read());
        auth()->setUser((new User)->forceFill(['id' => 2]));
        $this->assertSame(7, $read());
        $this->assertSame(7, $calls);
    }

    public function test_expiry_and_refresh_reload_operational_values(): void
    {
        $calls = 0;
        $load = function () use (&$calls) {
            return ++$calls;
        };
        $read = fn ($refresh = false) => $this->cache->remember('schedules', 1, [], $load, $refresh);
        $this->assertSame(1, $read());
        $this->assertSame(1, $read());
        $this->travel(11)->seconds();
        $this->assertSame(2, $read());
        $this->assertSame(3, $read(true));
        $this->assertSame(3, $read());
    }

    public function test_committed_query_builder_writes_invalidate_and_rollbacks_preserve_cache(): void
    {
        $this->assertSame(1, $this->cachedStatus());
        DB::beginTransaction();
        DB::table('mm_batches')->update(['status' => 2]);
        $this->assertSame(2, $this->cachedStatus(), 'Uncommitted reads bypass shared cache');
        DB::rollBack();
        $this->assertSame(1, $this->cachedStatus());
        DB::transaction(fn () => DB::table('mm_batches')->update(['status' => 3]));
        $this->assertSame(3, $this->cachedStatus());
        DB::table('mm_batches')->delete();
        $this->assertSame(0, $this->cachedStatus());
    }

    public function test_dispatch_writes_invalidate_related_batch_reads_without_flushing_other_modules(): void
    {
        Cache::put('other-module', 'preserved', 60);
        $calls = 0;
        $load = function () use (&$calls) {
            return ++$calls;
        };
        $read = fn () => $this->cache->remember('batch-grid', 1, [], $load);
        $this->assertSame(1, $read());
        DB::table('unrelated')->insert(['id' => 1]);
        $this->assertSame(1, $read());
        DB::table('mm_dispatches')->insert(['id' => 1, 'plant_id' => 1, 'status' => 1]);
        $this->assertSame(2, $read());
        DB::table('mm_dispatches')->update(['status' => 2]);
        $this->assertSame(3, $read());
        $this->assertSame('preserved', Cache::get('other-module'));
        $this->assertSame('array', config('cache.default'));
    }

    public function test_write_during_a_cache_miss_cannot_publish_old_data_under_the_new_version(): void
    {
        $this->assertSame('old', $this->cache->remember('batch-grid', 1, [], function () {
            DB::table('mm_batches')->update(['status' => 2]);

            return 'old';
        }));
        $this->assertSame('new', $this->cache->remember('batch-grid', 1, [], fn () => 'new'));
    }

    public function test_redis_outage_falls_back_and_loader_errors_propagate_once(): void
    {
        config(['production_cache.store' => 'unavailable-production-store']);
        $this->assertSame(1, $this->cachedStatus());
        $calls = 0;
        try {
            $this->cache->remember('dispatch-grid', 1, [], function () use (&$calls) {
                $calls++;
                throw new \RuntimeException('database read failed');
            });
            $this->fail('Database exceptions must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('database read failed', $e->getMessage());
        }
        $this->assertSame(1, $calls);
        DB::table('mm_batches')->update(['status' => 2]);
        $this->assertSame(2, $this->cachedStatus());
    }

    public function test_loader_errors_with_a_healthy_cache_are_not_cached_or_retried(): void
    {
        $calls = 0;
        try {
            $this->cache->remember('dashboard', 1, [], function () use (&$calls) {
                $calls++;
                throw new \RuntimeException('query failed');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('query failed', $e->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->assertSame('recovered', $this->cache->remember('dashboard', 1, [], fn () => 'recovered'));
    }

    public function test_disabled_cache_and_other_modules_always_read_directly(): void
    {
        $calls = 0;
        $load = function () use (&$calls) {
            return ++$calls;
        };
        $this->assertSame(1, $this->cache->remember('accounting', 1, [], $load));
        $this->assertSame(2, $this->cache->remember('accounting', 1, [], $load));
        config(['production_cache.enabled' => false]);
        $this->assertSame(3, $this->cache->remember('batch-grid', 1, [], $load));
        $this->assertSame(4, $this->cache->remember('batch-grid', 1, [], $load));
    }

    public function test_connection_namespaces_do_not_share_cached_results(): void
    {
        $this->assertSame(1, $this->cachedStatus());
        config(['database.connections.production_other_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('production_other_test');
        DB::statement('CREATE TABLE mm_batches (id INTEGER PRIMARY KEY, plant_id INTEGER, status INTEGER)');
        DB::table('mm_batches')->insert(['id' => 1, 'plant_id' => 1, 'status' => 8]);
        $this->assertSame(8, $this->cachedStatus());
        DB::setDefaultConnection('sqlite');
        $this->assertSame(1, $this->cachedStatus());
    }

    public function test_scheduling_endpoint_uses_cache_and_checks_authentication_before_reads(): void
    {
        DB::statement('CREATE TABLE mm_concrete_batching_schedules (id INTEGER PRIMARY KEY, plant_id INTEGER, schedule_date TEXT, status TEXT, deleted_at TEXT)');
        $controller = new class extends ConcreteBatchingScheduleController
        {
            protected function authorizeModule(string $action, ?string $module = null): void {}
        };
        $request = Request::create('/production/batching-schedules/data');
        DB::enableQueryLog();
        $this->assertSame([], $controller->getData($request)->getData(true)['schedules']);
        $count = count(DB::getQueryLog());
        $this->assertSame([], $controller->getData($request)->getData(true)['schedules']);
        $this->assertSame($count, count(DB::getQueryLog()), 'Second request avoids the scheduling SELECT');
        $controller->getData(Request::create('/production/batching-schedules/data', 'GET', ['refresh' => true]));
        $this->assertGreaterThan($count, count(DB::getQueryLog()));
        auth()->forgetUser();
        try {
            (new ConcreteBatchingScheduleController)->getData($request);
            $this->fail('Unauthenticated reads must be denied before cache access.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }
}
