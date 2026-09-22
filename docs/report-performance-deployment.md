# Report performance on Linux shared hosting

Exports previously called dispatchSync even when the response said queued. Background
mode now sends unified, sales/purchase register, machine summary and vehicle P&L
exports to a dedicated reports queue. Existing synchronous behavior remains the
default until a worker is installed. This frees web request slots; it does not make
the report's SQL or PDF rendering itself faster.

## Enable using the existing database and cron

Background reports use the database queue. Confirm with the host that CLI PHP 8.3+, cron,
flock and a process lasting up to 10 minutes are allowed. PHP CLI needs pcntl for
Laravel job timeouts. Use the host's actual PHP binary and account paths below.
Confirm mm_jobs and mm_failed_jobs exist before enabling; review pending migrations
before applying them. Do not run an unreviewed migration batch on production.

1. Deploy the changed PHP files and rebuild Vue assets with `npm ci` and
   `npm run build` on your build machine, then upload public/build.
2. Set these values in the production environment:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   REPORTS_ASYNC_EXPORTS=true
   REPORTS_QUEUE_CONNECTION=database
   REPORTS_QUEUE=reports
   DB_QUEUE_RETRY_AFTER=660
   ```

3. Install this cron entry (replace all example paths). Start with one worker to
   limit database and PDF memory pressure. flock prevents overlapping workers.

   ```cron
   * * * * * /usr/bin/flock -n /home/ACCOUNT/report-worker.lock /usr/bin/php /home/ACCOUNT/portal/artisan queue:work database --queue=reports --stop-when-empty --max-time=50 --timeout=600 --tries=1 --memory=256 >> /home/ACCOUNT/report-worker.log 2>&1
   ```

   `--max-time=50` is checked between jobs, not a hard 50-second job limit. The
   host must allow the current report to finish. Cron can add up to a minute of
   initial wait, and a backlog adds more. Increase concurrency only after measuring
   memory/CPU and getting hosting limits. A dedicated reports worker does not
   consume the application's other queues.

4. Run `php artisan config:cache` with the production CLI PHP. Check a small PDF
   and Excel export: the request should return quickly, progress should advance,
   and the downloaded report should contain the correct plant and filters.
   Check `php artisan queue:failed` and the worker log. Export status expires after
   one hour, so monitor backlog and keep queue waits below that window.
5. On deployment, run `php artisan queue:restart` with the same cache configuration
   as workers. For multiple web servers, generated files need shared storage; the
   current export code writes to this server's storage/app/public/reports.

Rollback: set REPORTS_ASYNC_EXPORTS=false and rebuild the config cache. Let queued
jobs drain before removing the cron entry. Do not clear the queue or cache during
the switch. Existing queued work stays on its original backend.

## Cache and queue configuration

Use CACHE_STORE=file on a single server or CACHE_STORE=database when web and worker
processes run on different servers. Both processes must share the cache store and
prefix so export progress is visible. Redis support is removed for now; keep
REPORTS_QUEUE_CONNECTION=database. If an existing deployment uses Redis, drain its
queued work with the previous release before deploying this change, replace its
cache/queue settings, rebuild configuration and restart workers. Sessions should
use file or database as well.

## Measure before claiming capacity

Compare the same plant, filters and dataset before/after: export request duration,
queue waiting time, generation duration, peak worker memory, and ordinary page p95
latency. Record database slow queries and EXPLAIN their plans on a staging copy.
The repository already contains report index migrations; verify deployed indexes
before adding duplicates. Test gradual concurrency against staging, not a live
1,000-user load test. Registered users and simultaneously active users are different
capacity targets. Shared hosting resource limits may still require a VPS upgrade.

Reference: https://laravel.com/docs/12.x/queues (workers, timeouts and retry_after).
