# Dropdown caching

The shared dropdown helpers now cache options for 120 seconds. This covers patrons,
sites, machines, personnel/drivers/operators, products and units, mix designs,
grades, taxes, ledgers, payment methods, pump rates and lookup tables. Vendor,
vehicle and mix-design option aliases reuse the cached helpers. The patron and
ledger dropdown HTTP endpoints also use this cache. Static option arrays do not
need caching. DetailedPatronsDropdown remains uncached because it returns complete
editable records with contacts, addresses and bank accounts. Controller-specific
queries that do not use these helpers remain unchanged.

Dropdowns use file caching by default. This avoids replacing a cheap dropdown SELECT
with multiple database-cache queries. The following settings are sufficient:

```dotenv
DROPDOWN_CACHE_ENABLED=true
DROPDOWN_CACHE_STORE=file
DROPDOWN_CACHE_TTL=120
```

Run `php artisan config:cache` after deployment and restart long-running workers.
Keep storage/framework/cache/data writable by the application. A PHP-only deployment
is sufficient for this change. For multiple web servers, use
DROPDOWN_CACHE_STORE=database with the same database and cache prefix on every server;
local file cache invalidation only reaches that server. Redis support is removed
for now. Replace any existing CACHE_STORE=redis or DROPDOWN_CACHE_STORE=redis setting
with file or database before rebuilding configuration.

Keys include the helper name, all arguments (including allowed entity IDs), database,
plant, entity, user, locale and timezone. Existing query authorization/scopes are
preserved; caching does not grant access to additional records. Serialized values
preserve collection and model APIs, and each hit returns fresh objects so mutations
by one caller cannot affect another caller.

Each helper declares its table dependencies in config/dropdowns.php. Successful
Laravel SQL writes to these tables rotate dependency versions, including bulk
updates, soft deletes/restores, and pivot changes. Invalidation happens after commit;
reads inside transactions bypass the cache. Old result keys expire automatically.
Version keys avoid serving a stale fill that races with invalidation. Invalidation
is table-wide, so edits in one plant can refresh another plant's cache too.

The SQL listener recognizes ordinary INSERT, UPDATE, DELETE, REPLACE and TRUNCATE
statements issued through Laravel. External database edits, stored-procedure side
effects, unusual SQL beginning with comments/CTEs, and other applications are not
observed; those changes become visible after the TTL. Call
`app(App\Services\DropdownCache::class)->invalidate(['mm_sites'])` explicitly for
such imports performed by this app. Include new related tables in the dependency
map when extending dropdown queries. Cache outages fall back to querying the
database; a failed invalidation can leave old options visible until the TTL expires.

Disable with DROPDOWN_CACHE_ENABLED=false and rebuild the config cache if necessary.

Production latency needs verification on the host. See the official
[Laravel cache documentation](https://laravel.com/docs/12.x/cache).
