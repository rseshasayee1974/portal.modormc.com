<?php

/*
Author: ragul-onemodo
Created: 2026-10-25 19:52:48 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductionReadCache
{
    public function remember(string $view, int $plantId, array $filters, Closure $load, bool $refresh = false): mixed
    {
        if (! config('production_cache.enabled') || $plantId < 1 ||
            ! in_array($view, ['batch-grid', 'dispatch-grid', 'dashboard', 'schedules'], true)) {
            return $load();
        }
        foreach (DB::getConnections() as $connection) {
            if ($connection->transactionLevel() > 0) {
                return $load();
            }
        }

        $context = [
            'plant_id' => $plantId, 'entity_id' => session('active_entity_id'),
            'user_id' => auth()->id(), 'role_id' => session('active_role_id'),
            'locale' => app()->getLocale(), 'timezone' => config('app.timezone'),
        ];
        try {
            $cache = Cache::store(config('production_cache.store'));
            $versionKey = $this->versionKey();
            $cache->add($versionKey, (string) Str::uuid(), 86400);
            $version = $cache->get($versionKey);
            if (! is_string($version)) {
                throw new \RuntimeException('Production cache version unavailable.');
            }
            $key = $versionKey.':'.$view.':'.hash('sha256', serialize([$version, $context, $filters]));
            if ($refresh) {
                $cache->forget($key);
            }
            $payload = $cache->get($key);
        } catch (\Throwable) {
            return $load();
        }
        if (is_array($payload) && array_key_exists('value', $payload)) {
            return $payload['value'];
        }

        // Database/validation errors propagate; only cache transport errors fall back.
        $value = $load();
        try {
            $ttl = (int) config($view === 'schedules' ? 'production_cache.schedule_ttl' : 'production_cache.ttl');
            if ($ttl > 0) {
                $cache->put($key, ['value' => $value], $ttl);
            }
        } catch (\Throwable) {
            // Successful database reads remain available during a Redis outage.
        }

        return $value;
    }

    public function onQuery(QueryExecuted $event): void
    {
        if (! config('production_cache.enabled') ||
            ! preg_match('/^\s*(insert|update|delete|replace|truncate|alter|drop)\b/i', $event->sql)) {
            return;
        }
        // Match the write target, not tables mentioned by an INSERT ... SELECT.
        $tables = config('production_cache.tables', []);
        if (! preg_match('/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from|truncate(?:\s+table)?|alter\s+table|drop\s+table)\s+[`"\[]?([a-z_][a-z0-9_]*)/i', $event->sql, $match) ||
            ! in_array(strtolower($match[1]), $tables, true)) {
            return;
        }
        $key = $this->versionKey($event->connectionName);
        $invalidate = function () use ($key) {
            try {
                Cache::store(config('production_cache.store'))->put($key, (string) Str::uuid(), 86400);
            } catch (\Throwable) {
                // Short TTL bounds staleness if invalidation cannot reach Redis.
            }
        };
        if ($event->connection->transactionLevel() > 0) {
            $event->connection->afterCommit($invalidate);
        } else {
            $invalidate();
        }
    }

    private function versionKey(?string $connection = null): string
    {
        $db = DB::connection($connection);

        return 'production-reads:v1:'.hash('sha256', $db->getName().'|'.$db->getDatabaseName());
    }
}
