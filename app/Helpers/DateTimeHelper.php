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
                return $entity->time_zone;
            }
        }

        return 'Asia/Kolkata';
    }

    /**
     * Return now() instance in the entity's specified timezone.
     */
    public static function nowForEntity(?int $entityId = null): Carbon
    {
        return now(self::getEntityTimezone($entityId));
    }
}
