<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\User;
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
 *   app(PlantContextService::class)->gstin()       — returns string|null
 *   app(PlantContextService::class)->requirePlantId() — returns int or aborts 403
 *   PlantContextService::current()                 — static alias (facade-style)
 */
class PlantContextService
{
    /** Saved preferences may refer to a removed, restricted, or reassigned plant. */
    public function validDefaultPlant(User $user, ?bool $isSuperAdmin = null): ?Plant
    {
        if (!$user->default_entity_id || !$user->default_plant_id) {
            return null;
        }

        $isSuperAdmin ??= $user->isSystemAdmin();
        $plant = Plant::where('id', $user->default_plant_id)
            ->where('entity_id', $user->default_entity_id)
            ->where('is_active', 1)
            ->whereHas('entity', function ($query) use ($isSuperAdmin) {
                if (!$isSuperAdmin) {
                    $query->where('is_suspended', 0);
                }
            })->first();

        if ($plant && !$isSuperAdmin && !\App\Models\EntityUser::where('user_id', $user->id)
            ->where('entity_id', $plant->entity_id)
            ->where(fn ($query) => $query->whereNull('plant_id')->orWhere('plant_id', $plant->id))
            ->exists()) {
            return null;
        }

        return $plant;
    }

    /** Include restricted workspaces so their existing access checks still apply. */
    public function hasWorkspaces(User $user, ?bool $isSuperAdmin = null): bool
    {
        $plants = Plant::query()->whereHas('entity');
        if (!($isSuperAdmin ?? $user->isSystemAdmin())) {
            $plants->whereExists(function ($query) use ($user) {
                $query->selectRaw('1')->from('mm_entity_users')
                    ->where('user_id', $user->id)
                    ->whereNull('mm_entity_users.deleted_at')
                    ->whereColumn('mm_entity_users.entity_id', 'mm_plants.entity_id')
                    ->where(function ($assignment) {
                        $assignment->whereNull('mm_entity_users.plant_id')
                            ->orWhereColumn('mm_entity_users.plant_id', 'mm_plants.id');
                    });
            });
        }

        return $plants->exists();
    }

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
        if ($user && ($plant = $this->validDefaultPlant($user))) {
            // Re-hydrate the session so downstream code using raw session() still works
            Session::put('active_entity_id', $plant->entity_id);
            Session::put('active_plant_id', $plant->id);
            $gstin = $plant->gstin;
            if ($gstin) {
                Session::put('gstin', $gstin);
            }
            return (int) $plant->id;
        }

        return null;
    }

    /**
     * Resolve the active plant GSTIN.
     * Session > plant model > null.
     */
    public function gstin(): ?string
    {
        $fromSession = Session::get('gstin') ?: Session::get('gst');
        if ($fromSession) {
            return $fromSession;
        }

        $plantId = $this->plantId();
        if ($plantId) {
            $gstin = Plant::where('id', $plantId)->value('gstin');
            if ($gstin) {
                Session::put('gstin', $gstin);
                return $gstin;
            }
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
            if (!Session::has('active_entity_id')) {
                Session::put('active_entity_id', (int) $fromSession);
            }
            return (int) $fromSession;
        }

        $user = Auth::user();
        if ($user && $user->active_entity_id) {
            Session::put('active_entity_id', $user->active_entity_id);
            return (int) $user->active_entity_id;
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
