<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 14:25:55 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\BatchMoisture;

use App\Models\CustomSetting;
use App\Models\Plant;
use Illuminate\Support\Str;

final class PlantCalculations
{
    private const MOISTURE_FORMULAS = [
        'modomines' => Modomines::class,
    ];

    public static function profile(?Plant $plant): string
    {
        if (!$plant) return 'default';

        $name = Str::slug((string) $plant->name);
        $isModomines = preg_match('/\bmodo[\s-]*mines\b/i', (string) $plant->name)
            || preg_match('/\bmodo[\s-]*mines\b/i', (string) $plant->code);
        if ($isModomines) return 'modomines';

        return $name ?: 'default';
    }

    public static function moistureFormula(?Plant $plant): ?string
    {
        if (!$plant) return null;
        $setting = CustomSetting::getForModule($plant->id, 'batching')['moisture_profile'] ?? 'auto';
        $profile = $setting === 'auto' ? self::profile($plant) : $setting;
        return self::MOISTURE_FORMULAS[$profile] ?? null;
    }
}
