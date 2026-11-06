<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateTimeHelper
{
    public const TIMEZONE = 'Asia/Kolkata';
    /**
     * All entities use Indian Standard Time (UTC+05:30).
     */
    public static function getEntityTimezone(?int $entityId = null): string
    {
        return self::TIMEZONE;
    }

    public static function validTimezone(?string $timezone): string
    {
        return self::TIMEZONE;
    }

    public static function timezoneForPlant(?int $plantId): string
    {
        return self::TIMEZONE;
    }

    /** Always restore process state, including on errors and between queue jobs. */
    public static function inTimezone(string $timezone, callable $callback): mixed
    {
        $previous = date_default_timezone_get();
        $previousConfig = config('app.timezone');
        $timezone = self::validTimezone($timezone);
        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);
        try {
            return $callback();
        } finally {
            date_default_timezone_set($previous);
            config(['app.timezone' => $previousConfig]);
        }
    }

    /**
     * Return now() instance in the entity's specified timezone.
     */
    public static function nowForEntity(?int $entityId = null): Carbon
    {
        return now(self::getEntityTimezone($entityId));
    }
}
