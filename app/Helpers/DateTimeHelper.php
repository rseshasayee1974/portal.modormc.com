<?php

namespace App\Helpers;

use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class DateTimeHelper
{
    /**
     * Get the timezone for a specific entity id or from current session.
     * Fallback to Indian Standard Time (Asia/Kolkata).
     */
    public static function getEntityTimezone(?int $entityId = null): string
    {
        $id = $entityId ?? Session::get('active_entity_id') ?? Session::get('entity_id');
        
        if ($id) {
            // Use cache or static variable if called multiple times in one request?
            // For now, direct find is fine as it's often already loaded in the request.
            $entity = Entity::find($id);
            if ($entity && !empty($entity->time_zone)) {
                return self::validTimezone($entity->time_zone);
            }
        }

        return 'Asia/Kolkata';
    }

    public static function validTimezone(?string $timezone): string
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)
            ? $timezone : 'Asia/Kolkata';
    }

    public static function timezoneForPlant(?int $plantId): string
    {
        $entityId = $plantId ? \App\Models\Plant::whereKey($plantId)->value('entity_id') : null;
        return $entityId ? self::getEntityTimezone((int) $entityId) : 'Asia/Kolkata';
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
