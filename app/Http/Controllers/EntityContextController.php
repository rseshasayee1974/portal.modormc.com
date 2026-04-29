<?php

namespace App\Http\Controllers;

use App\Models\EntityUser;
use App\Models\Entity;
use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class EntityContextController extends Controller
{
    /**
     * Show the entity selection page for the authenticated user.
     * All entities this user can access are fetched from mm_entity_users.
     */
    public function index()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSystemAdmin();

        if ($isSuperAdmin) {
            // Super Admin sees ALL entities; no role context shown
            $entityAccess = Entity::all()->map(fn ($e) => [
                'entity_id'    => $e->id,
                'entity_name'  => $e->legal_name,
                'entity_alias' => $e->alias ?? null,
                'entity_logo'  => $e->logo_file ?? null,
                'role_name'    => 'Super Administrator',
                'is_active'    => $e->id === (int) session('active_entity_id'),
            ])->values();
        } else {
            // Normal users only see their assigned entities
            $entityAccess = EntityUser::with(['entity', 'role'])
                ->where('user_id', $user->id)
                ->get()
                ->map(fn ($eu) => [
                    'entity_id'    => $eu->entity_id,
                    'entity_name'  => $eu->entity->legal_name ?? 'Unknown Entity',
                    'entity_alias' => $eu->entity->alias ?? null,
                    'entity_logo'  => $eu->entity->logo_file ?? null,
                    'role_name'    => $eu->role->name ?? 'No Role',
                    'is_active'    => $eu->entity_id === (int) session('active_entity_id'),
                ])
                ->values();
        }

        return Inertia::render('EntitySelect/Index', [
            'entityAccess' => $entityAccess,
        ]);
    }

    /**
     * Set the active entity for this session.
     * Returns available plants for the selected entity so the user can pick one.
     */
    public function store(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $entityId = (int) $request->entity_id;

        // System Admins can switch freely — no mm_entity_users check required
        if (!$user->isSystemAdmin()) {
            $entityUser = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $entityId)
                ->first();

            if (!$entityUser) {
                return back()->withErrors(['entity_id' => 'You do not have access to this entity.']);
            }
        }

        // Persist the entity context in session
        session(['active_entity_id' => $entityId]);

        // Clear any previously active plant when switching entity
        session()->forget('active_plant_id');

        // Return only allowed plants for this user/entity.
        // If user has at least one row with plant_id NULL, treat it as full plant access for that entity.
        $plantsQuery = Plant::where('entity_id', $entityId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'is_main')
            ->orderByDesc('is_main')
            ->orderBy('name');

        if (!$user->isSystemAdmin()) {
            $entityAssignments = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $entityId)
                ->get(['plant_id']);

            $hasEntityWideAccess = $entityAssignments->contains(fn ($row) => $row->plant_id === null);

            if (!$hasEntityWideAccess) {
                $allowedPlantIds = $entityAssignments
                    ->pluck('plant_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $plantsQuery->whereIn('id', $allowedPlantIds);
            }
        }

        $plants = $plantsQuery->get();

        return response()->json([
            'status' => 'entity_set',
            'plants' => $plants,
        ]);
    }

    /**
     * Set the active plant for this session.
     * Called after entity selection when user picks a plant.
     */
    public function setPlant(Request $request)
    {
        $request->validate([
            'plant_id' => 'required|integer',
        ]);

        $user     = Auth::user();
        $entityId = (int) session('active_entity_id');
        $plantId  = (int) $request->plant_id;

        if (!$entityId) {
            return response()->json(['error' => 'No active entity set.'], 422);
        }

        // Verify the plant belongs to the active entity
        $plant = Plant::where('id', $plantId)
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->first();

        if (!$plant) {
            return response()->json(['error' => 'Invalid plant for the selected entity.'], 422);
        }

        // For non-System Admins, verify access via mm_entity_users.
        // plant_id NULL means entity-level access to all plants.
        if (!$user->isSystemAdmin()) {
            $hasAccess = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $entityId)
                ->where(function ($query) use ($plantId) {
                    $query->where('plant_id', $plantId)
                        ->orWhereNull('plant_id');
                })
                ->exists();

            if (!$hasAccess) {
                return response()->json(['error' => 'You do not have access to this plant.'], 403);
            }
        }

        session(['active_plant_id' => $plantId]);

        return response()->json([
            'status'   => 'plant_set',
            'plant_id' => $plantId,
        ]);
    }
}
