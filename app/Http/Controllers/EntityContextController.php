<?php

namespace App\Http\Controllers;

use App\Models\EntityUser;
use App\Models\Entity;
use App\Models\Plant;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class EntityContextController extends Controller
{
    /**
     * Show the entity selection page for the authenticated user.
     * All entities this user can access are fetched from mm_entity_users.
     */
    /**
     * Show the facility selection page for the authenticated user.
     * All facilities/plants this user can access are fetched based on assignment.
     */
    public function index()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSystemAdmin();

        $plants = collect();

        if ($isSuperAdmin) {
            // Super Admin sees ALL plants
            $allPlants = Plant::where('is_active', '!=', 0)->with('entity')->get();
            foreach ($allPlants as $p) {
                $plants->push([
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'code'          => $p->code,
                    'is_main'       => $p->is_main,
                    'is_active'     => $p->is_active,
                    'logo_path'     => $p->logo_path,
                    'email_address' => $p->email_address,
                    'mobile_number' => $p->mobile_number,
                    'entity_id'     => $p->entity_id,
                    'entity_name'   => $p->entity->legal_name ?? 'Unknown Organization',
                    'entity_logo'   => $p->entity->logo_file ?? null,
                    'role_name'     => 'Super Administrator',
                ]);
            }
        } else {
            // Normal users see plants they are explicitly assigned to (or all plants if entity assignment is global)
            $entityUsers = EntityUser::with(['entity', 'role'])
                ->where('user_id', $user->id)
                ->get();

            foreach ($entityUsers as $row) {
                $entity = $row->entity;
                // Skip if organization is suspended
                if (!$entity || $entity->is_suspended != 0) {
                    continue;
                }

                $roleName = $row->role->name ?? 'No Role';

                if ($row->plant_id) {
                    // Specific plant access
                    $p = Plant::where('id', $row->plant_id)
                        ->where('entity_id', $row->entity_id)
                        ->where('is_active', '!=', 0)
                        ->first();

                    if ($p) {
                        $plants->push([
                            'id'            => $p->id,
                            'name'          => $p->name,
                            'code'          => $p->code,
                            'is_main'       => $p->is_main,
                            'is_active'     => $p->is_active,
                            'logo_path'     => $p->logo_path,
                            'email_address' => $p->email_address,
                            'mobile_number' => $p->mobile_number,
                            'entity_id'     => $p->entity_id,
                            'entity_name'   => $entity->legal_name,
                            'entity_logo'   => $entity->logo_file,
                            'role_name'     => $roleName,
                        ]);
                    }
                } else {
                    // Entity-wide access
                    $entityPlants = Plant::where('entity_id', $row->entity_id)
                        ->where('is_active', '!=', 0)
                        ->get();

                    foreach ($entityPlants as $p) {
                        $plants->push([
                            'id'            => $p->id,
                            'name'          => $p->name,
                            'code'          => $p->code,
                            'is_main'       => $p->is_main,
                            'is_active'     => $p->is_active,
                            'logo_path'     => $p->logo_path,
                            'email_address' => $p->email_address,
                            'mobile_number' => $p->mobile_number,
                            'entity_id'     => $p->entity_id,
                            'entity_name'   => $entity->legal_name,
                            'entity_logo'   => $entity->logo_file,
                            'role_name'     => $roleName,
                        ]);
                    }
                }
            }
        }

        // De-duplicate plants by id
        $plants = $plants->unique('id')->values();

        // 1. Auto-redirect if user has defaults set (only if session is currently empty)
        if (!session('active_entity_id') && $user->default_entity_id && $user->default_plant_id) {
            session([
                'active_entity_id' => $user->default_entity_id,
                'active_plant_id'  => $user->default_plant_id
            ]);
            return redirect()->route('dashboard');
        }

        // 2. Auto-redirect if exactly one plant is available and active (is_active == 1)
        if ($plants->count() === 1 && $plants->first()['is_active'] === 1) {
            $p = $plants->first();
            if (session('active_entity_id') != $p['entity_id'] || session('active_plant_id') != $p['id']) {
                session([
                    'active_entity_id' => $p['entity_id'],
                    'active_plant_id'  => $p['id']
                ]);
                return redirect()->route('dashboard');
            }
        }

        return Inertia::render('EntitySelect/Index', [
            'plants'   => $plants,
            'defaults' => [
                'entity_id' => $user->default_entity_id,
                'plant_id'  => $user->default_plant_id,
            ],
        ]);
    }

    /**
     * Set the active entity for this session (Deprecated but kept for safety).
     */
    public function store(Request $request)
    {
        return response()->json(['message' => 'Deprecated.'], 410);
    }

    /**
     * Set the active plant for this session.
     */
    public function setPlant(Request $request)
    {
        $request->validate([
            'plant_id' => 'required|integer',
        ]);

        $user     = Auth::user();
        $plantId  = (int) $request->plant_id;

        // Retrieve the plant first to find the entity_id
        $plant = Plant::where('id', $plantId)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$plant) {
            return response()->json(['error' => 'Invalid facility or it is inactive.'], 422);
        }

        if ($plant->is_active === -1) {
            return response()->json(['error' => 'Access Restricted: This facility is currently inactive.'], 403);
        }

        $entityId = $plant->entity_id;

        // For non-System Admins, verify access via mm_entity_users.
        if (!$user->isSystemAdmin()) {
            // Check if the entity itself is suspended
            $entity = Entity::find($entityId);
            if ($entity && $entity->is_suspended != 0) {
                return response()->json(['error' => 'Access Restricted: The organization is suspended.'], 403);
            }

            $hasAccess = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $entityId)
                ->where(function ($query) use ($plantId) {
                    $query->where('plant_id', $plantId)
                        ->orWhereNull('plant_id');
                })
                ->exists();

            if (!$hasAccess) {
                return response()->json(['error' => 'You do not have access to this facility.'], 403);
            }
        }

        session([
            'active_entity_id' => $entityId,
            'active_plant_id'  => $plantId
        ]);

        // Always save selected entity and plant as the user's default / last login context
        $user->update([
            'default_entity_id' => $entityId,
            'default_plant_id'  => $plantId,
        ]);

        return response()->json([
            'status'   => 'plant_set',
            'plant_id' => $plantId,
        ]);
    }

    /**
     * Toggle the suspended status of an entity.
     * Normal User -> toggles between 0 and -1 (Maintenance/Unpaid)
     * Super Admin -> toggles between 0 and 1 (Inactive/Platform Suspended)
     */
    public function toggleSuspension(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $entityId = (int) $request->entity_id;

        // Check if user has access to this entity if not Super Admin
        if (!$user->isSystemAdmin()) {
            $hasAccess = EntityUser::where('user_id', $user->id)
                ->where('entity_id', $entityId)
                ->exists();

            if (!$hasAccess) {
                return response()->json(['error' => 'Unauthorized access.'], 403);
            }
        }

        $entity = Entity::findOrFail($entityId);

        $oldSuspendedState = $entity->is_suspended;

        if ($entity->is_suspended != 0) {
            // Unsuspend/Activate - Super Admin or higher role user only
            if (!$user->isSystemAdmin()) {
                return response()->json(['error' => 'Only a Super Administrator can reactivate a suspended organization.'], 403);
            }
            $entity->is_suspended = 0;
            $status = 'activated';
        } else {
            // Suspend
            if ($user->isSystemAdmin()) {
                $entity->is_suspended = 1; // Super Admin suspended
                $status = 'suspended_by_admin';
            } else {
                $entity->is_suspended = -1; // User suspended (Unpaid / Maintenance)
                $status = 'suspended_by_user';
            }
        }

        $entity->save();

        // 1. Separate Log File (storage/logs/suspension.log)
        $logMessage = sprintf(
            "[%s] USER_ID: %d | ENTITY_ID: %d | ENTITY_NAME: %s | ACTION: %s | OLD_STATE: %d | NEW_STATE: %d | IP: %s\n",
            now()->toIso8601String(),
            $user->id,
            $entity->id,
            $entity->legal_name,
            $status,
            $oldSuspendedState,
            $entity->is_suspended,
            $request->ip()
        );
        File::append(storage_path('logs/suspension.log'), $logMessage);

        // Database Activity Log (mm_activity_log) was removed

        return response()->json([
            'status' => 'success',
            'action' => $status,
            'is_suspended' => $entity->is_suspended,
        ]);
    }
}