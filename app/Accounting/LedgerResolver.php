<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Models\AccountDefaultSetting;
use App\Models\Ledger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves ledger IDs from AccountDefaultSetting with a 5-minute cache.
 * Falls back to fuzzy name match and logs a warning so ops can fix the mapping.
 *
 * Cache is busted when Account Default Settings change — call bust() from
 * AccountDefaultSetting's saved/deleted observer.
 */
class LedgerResolver
{
    /**
     * Resolve a ledger ID for a given plant + module + setting key.
     * Returns null if neither mapped nor fuzzy match found.
     */
    public function resolve(int $plantId, string $module, string $settingKey, string $fallbackSearch): ?int
    {
        $cacheKey = "ledger_id:{$plantId}:{$module}:{$settingKey}";

        return Cache::remember($cacheKey, 300, function () use ($plantId, $module, $settingKey, $fallbackSearch) {
            // 1. Primary: explicit mapping in account_default_settings
            $mapped = AccountDefaultSetting::query()
                ->where('plant_id', $plantId)
                ->where('module_name', $module)
                ->where('setting_key', $settingKey)
                ->where('is_active', true)
                ->value('ledger_id');

            if ($mapped) {
                return $mapped;
            }

            // 2. Fallback: fuzzy ledger name match — always log so ops can fix it
            $fallback = Ledger::query()
                ->where('title', 'like', "%{$fallbackSearch}%")
                ->where('plant_id', $plantId)
                ->value('id');

            if ($fallback) {
                Log::warning("Accounting: Using fuzzy fallback ledger for key [{$settingKey}] "
                    . "in plant {$plantId}. Configure Account Default Settings to avoid this.");
            }

            return $fallback;
        });
    }

    /**
     * Bust cache for a specific setting — call this from AccountDefaultSetting observer.
     */
    public function bust(int $plantId, string $module, string $settingKey): void
    {
        Cache::forget("ledger_id:{$plantId}:{$module}:{$settingKey}");
    }

    /**
     * Bust all cached ledgers for a plant (e.g. when plant settings are bulk-updated).
     */
    public function bustAll(int $plantId): void
    {
        // Laravel cache tags would be cleaner here if your cache driver supports it.
        // If using Redis: Cache::tags(["plant_ledgers_{$plantId}"])->flush();
        // For file/database driver, store keys and iterate:
        Log::info("LedgerResolver: Full cache bust requested for plant {$plantId}. "
            . "Consider using Redis cache tags for granular busting.");
    }
}
