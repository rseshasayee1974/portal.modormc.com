<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DropdownCache
{
    public function remember(string $name, array $arguments, Closure $load): mixed
    {
        $tables = config("dropdowns.dependencies.{$name}", []);
        if (!config('dropdowns.enabled') || config('dropdowns.ttl') <= 0 || !$tables) {
            return $load();
        }

        // Never read shared cache or publish uncommitted dropdown records.
        foreach (DB::getConnections() as $connection) {
            if ($connection->transactionLevel() > 0) {
                return $load();
            }
        }

        $context = [
            'database' => DB::connection()->getDatabaseName(),
            'connection' => DB::getDefaultConnection(),
            'plant' => app(PlantContextService::class)->plantId(),
            'entity' => app(PlantContextService::class)->entityId(),
            'user' => auth()->id(),
            'locale' => app()->getLocale(),
            'timezone' => config('app.timezone'),
        ];

        try {
            $cache = Cache::store(config('dropdowns.store'));
            $versions = [];
            $versionValues = $cache->many(array_map(fn ($table) => $this->versionKey($table), $tables));
            foreach ($tables as $table) {
                $versionKey = $this->versionKey($table);
                $version = $versionValues[$versionKey] ?? null;
                if ($version === null) {
                    $cache->add($versionKey, (string) Str::uuid(), now()->addYear());
                    $version = $cache->get($versionKey);
                    if ($version === null) {
                        throw new \RuntimeException('Dropdown cache could not initialize.');
                    }
                }
                $versions[$table] = $version;
            }
            $key = 'dropdowns:v1:'.hash('sha256', serialize([$name, $arguments, $context, $versions]));
            $payload = $cache->get($key);
        } catch (\Throwable $e) {
            // Dropdowns still work if the optional cache is unavailable.
            return $load();
        }

        if (is_string($payload)) {
            // Keep Eloquent collections/relations and give each caller its own copy.
            return unserialize($payload);
        }

        // Query errors must propagate, never be mistaken for cache outages.
        $result = $load();
        try {
            $cache->put($key, serialize($result), (int) config('dropdowns.ttl', 120));
        } catch (\Throwable $e) {
            // A failed cache write must not discard successfully loaded options.
        }

        return $result;
    }

    public function invalidate(array $tables): void
    {
        try {
            $cache = Cache::store(config('dropdowns.store'));
            foreach (array_unique($tables) as $table) {
                $cache->put($this->versionKey($table), (string) Str::uuid(), now()->addYear());
            }
        } catch (\Throwable $e) {
            // Do not fail an already committed write; the short TTL bounds staleness.
        }
    }

    public function onQuery(QueryExecuted $event): void
    {
        if (!config('dropdowns.enabled') || !preg_match('/^\s*(insert|update|delete|replace|truncate)\b/i', $event->sql)) {
            return;
        }

        $tables = array_unique(array_merge(...array_values(config('dropdowns.dependencies', []))));
        $changed = array_values(array_filter($tables, fn ($table) => preg_match('/\b'.preg_quote($table, '/').'\b/i', $event->sql)));
        if (!$changed) {
            return;
        }

        // Includes query-builder mass updates and pivot syncs that skip model events.
        // A rollback discards this callback, leaving the existing cache valid.
        if ($event->connection->transactionLevel() > 0) {
            $event->connection->afterCommit(fn () => $this->invalidate($changed));
        } else {
            $this->invalidate($changed);
        }
    }

    private function versionKey(string $table): string
    {
        return 'dropdowns:version:'.$table;
    }
}
