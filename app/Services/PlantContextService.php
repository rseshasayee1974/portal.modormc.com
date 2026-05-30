<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * PlantContextService — Single source of truth for the active plant & entity.
 *
 * Resolves the active_plant_id in this priority order:
 *   1. Session key  'active_plant_id'       (set when user switches plant)
 *   2. User column  users.default_plant_id  (saved preference, survives session loss)
 *   3. Null         (no plant context — caller must handle redirect)
 *
 * Why this matters:
 *   All 200+ usages of session('active_plant_id') call the session store on
 *   every invocation. In a load-balanced deployment with sticky-sessions disabled,
 *   a request routed to a different node before the session write propagates would
 *   get null. This service adds the `default_plant_id` safety net and makes the
 *   resolution logic easy to swap out (e.g., to JWT claims) in the future.
 *
 * Usage:
 *   app(PlantContextService::class)->plantId()     — returns int|null
 *   app(PlantContextService::class)->entityId()    — returns int|null
 *   app(PlantContextService::class)->requirePlantId() — returns int or aborts 403
 *   PlantContextService::current()                 — static alias (facade-style)
 */
class PlantContextService
{
    /**
     * Resolve the active plant ID.
     * Session > user default > null.
     */
    public function plantId(): ?int
    {
        $fromSession = Session::get('active_plant_id');
        if ($fromSession) {
            return (int) $fromSession;
        }

        // Graceful fallback: use the user's saved default
        $user = Auth::user();
        if ($user && $user->default_plant_id) {
            // Re-hydrate the session so downstream code using raw session() still works
            Session::put('active_plant_id', $user->default_plant_id);
            return (int) $user->default_plant_id;
        }

        return null;
    }

    /**
     * Resolve the active entity ID.
     * Session > user default > null.
     */
    public function entityId(): ?int
    {
        $fromSession = Session::get('active_entity_id');
        if ($fromSession) {
            return (int) $fromSession;
        }

        $user = Auth::user();
        if ($user && $user->default_entity_id) {
            Session::put('active_entity_id', $user->default_entity_id);
            return (int) $user->default_entity_id;
        }

        return null;
    }

    /**
     * Return the plant ID or abort with 403 if none is available.
     * Use this in controllers/services that absolutely require a plant context.
     */
    public function requirePlantId(): int
    {
        $plantId = $this->plantId();

        abort_if(
            is_null($plantId),
            403,
            'No active plant selected. Please select a plant to continue.'
        );

        return $plantId;
    }

    /**
     * Static convenience accessor — mirrors the singleton from the container.
     */
    public static function current(): static
    {
        return app(static::class);
    }
}
