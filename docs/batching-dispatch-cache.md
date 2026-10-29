<!--
Author: ragul-onemodo
Created: 2026-10-25 19:55:07 Asia/Calcutta (UTC+05:30)
-->

# Batching and dispatch Redis cache

Only production read views use the explicit Redis store through `ProductionReadCache`:

| View | Read data cached | TTL |
| --- | --- | --- |
| Batch index | Batch grid including dispatch and invoice display fields | 15 seconds |
| Batch detail | Dispatch ticket query results for the selected batch | 15 seconds |
| Production dashboard | KPIs, charts, production metrics and recipe data | 15 seconds |
| Batching schedules | Filtered schedule rows, pour tracking and metrics | 10 seconds |

Authorization remains before controller cache reads. Keys include database/connection, plant, entity, user, role, locale, timezone, view and filters. Batch index/detail and dashboard/schedules require an active plant; they never substitute an arbitrary first plant. Batch detail enforces batch and dispatch plant ownership before returning cached dispatch data.

Generation keys invalidate these production read caches after relevant SQL writes commit, including query-builder updates and jobs using the Laravel connection. Rollbacks preserve the previous generation. A change in one plant conservatively invalidates production reads in all plants in that database; cached payloads remain isolated by plant. No unrelated cache keys are cleared. Dependencies include batches, dispatches, schedules, sales orders, invoice display data, and related master tables, listed in `config/production_cache.php`. These related modules only invalidate production reads; this change does not add Redis reads to those modules.

Reads inside database transactions bypass shared caching. Save operations, number generation, capacity checks, invoice posting, dispatch payments and source validation use the database directly. Redis read/write failures fall back to database reads; query and validation errors propagate. If invalidation fails or data changes outside Laravel (for example, a direct database import), staleness is bounded by the view TTL. A request already in progress can return its earlier snapshot. Schedule hydration/progress display can lag by up to 10 seconds. Append `refresh=1` to force a fresh view; this is a read refresh and never reserves a number or quantity.

## Configuration

Keep the application's default store unchanged for other modules. The local `.env` was returned to its previous file store, with Predis retained for the dedicated Redis connection:

```dotenv
CACHE_STORE=file
CACHE_DRIVER=file
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
PRODUCTION_READ_CACHE_ENABLED=true
PRODUCTION_READ_CACHE_STORE=redis
```

Use the actual private Redis host, credentials and deployment-specific key prefixes on staging/production. Do not put credentials in `VITE_*` variables. Predis is already a Composer dependency. Sessions and queues are not switched by this change. Automated tests disable the production cache by default; the focused tests enable an isolated array store explicitly.

After deploying code and reviewing server environment values, rebuild Laravel's configuration cache using the deployment's usual procedure. Restart long-running workers so they load the SQL invalidation listener. Do not flush Redis or run `cache:clear` against a shared Redis database. Disabling `PRODUCTION_READ_CACHE_ENABLED` and reloading configuration returns these reads directly to SQL.

No Vue/JavaScript changes or Vite rebuild are required for this backend change. No production deployment or business-data migration is performed by enabling this local cache.

## Verification

Run `php -d memory_limit=768M vendor/bin/phpunit tests/Feature/ProductionReadCacheTest.php tests/Feature/BatchCreationSourceTest.php` for cache isolation, expiry, refresh, transaction rollback/commit, dispatch invalidation, unrelated cache preservation, failure fallback, connection namespaces and scheduling controller reads, alongside existing batch-source validation tests. Live Redis verification should use a disposable key prefix and an in-memory SQLite database, preserving application keys and business data.

Verified on 2026-10-25: the cache and batch-source tests passed **17 tests, 91 assertions**. The combined cache/batch-source/accounting-workspace/patron-statement regression run passed **71 tests, 921 assertions**, with one expected SQLite concurrency skip. A real Redis/Predis smoke check passed hit/miss, commit invalidation, transaction bypass and rollback preservation using an isolated key prefix and in-memory database; its disposable generation key was removed. PHP syntax checks and focused Pint passed. No production browser acceptance or deployment is claimed.
