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
                ->groupBy('entity_id')
                ->map(fn ($group) => [
                    'entity_id'    => $group->first()->entity_id,
                    'entity_name'  => $group->first()->entity->legal_name ?? 'Unknown Entity',
                    'entity_alias' => $group->first()->entity->alias ?? null,
                    'entity_logo'  => $group->first()->entity->logo_file ?? null,
                    'role_name'    => $group->first()->role->name ?? 'No Role',
                    'is_active'    => $group->first()->entity_id === (int) session('active_entity_id'),
                ])
                ->values();
        }

        // 1. Auto-redirect if user has defaults set (only if session is currently empty)
        if (!session('active_entity_id') && $user->default_entity_id && $user->default_plant_id) {
            session([
                'active_entity_id' => $user->default_entity_id,
                'active_plant_id'  => $user->default_plant_id
            ]);
            return redirect()->route('dashboard');
        }

        // 2. Auto-redirect if exactly one entity and one plant are available
        // We do this even if the session is set, because if they only have ONE choice, 
        // there is no point in showing the selection screen.
        if ($entityAccess->count() === 1) {
            $entityId = $entityAccess[0]['entity_id'];
            
            $plantsQuery = Plant::where('entity_id', $entityId)->where('is_active', true);
            
            if (!$isSuperAdmin) {
                // For regular users, check their specific assignments
                $assignments = EntityUser::where('user_id', $user->id)
                    ->where('entity_id', $entityId)
                    ->get(['plant_id']);
                
                $hasGlobalAccess = $assignments->contains(fn($a) => is_null($a->plant_id));
                
                if (!$hasGlobalAccess) {
                    $plantsQuery->whereIn('id', $assignments->pluck('plant_id'));
                }
            }
            
            $plants = $plantsQuery->get();
            
            // If there's exactly one plant available for this single entity
            if ($plants->count() === 1) {
                $plantId = $plants->first()->id;
                
                // Only redirect if the current session doesn't match the only choice,
                // or if we're just landing here for the first time.
                if (session('active_entity_id') != $entityId || session('active_plant_id') != $plantId) {
                    session([
                        'active_entity_id' => $entityId,
                        'active_plant_id'  => $plantId
                    ]);
                    return redirect()->route('dashboard');
                }
            }
        }

        return Inertia::render('EntitySelect/Index', [
            'entityAccess' => $entityAccess,
            'defaults'     => [
                'entity_id' => $user->default_entity_id,
                'plant_id'  => $user->default_plant_id,
            ],
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

        // Save as default if requested
        if ($request->boolean('set_as_default')) {
            $user->update([
                'default_entity_id' => $entityId,
                'default_plant_id'  => $plantId,
            ]);
        }

        return response()->json([
            'status'   => 'plant_set',
            'plant_id' => $plantId,
        ]);
    }
}
