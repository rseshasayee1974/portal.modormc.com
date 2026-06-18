<?php

namespace App\Http\Controllers;

use App\Models\EntityUser;
use App\Models\Entity;
use App\Models\Plant;
use App\Services\Audit\AuditLogger;
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
    public function index()
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSystemAdmin();

        if ($isSuperAdmin) {
            // Super Admin sees ALL entities; no role context shown
            $entityAccess = Entity::with(['addresses', 'contacts'])->get()->map(fn ($e) => [
                'entity_id'    => $e->id,
                'entity_name'  => $e->legal_name,
                'entity_alias' => $e->alias ?? null,
                'entity_logo'  => $e->logo_file ?? null,
                'role_name'    => 'Super Administrator',
                'is_active'    => $e->id === (int) session('active_entity_id'),
                'is_suspended' => (int) $e->is_suspended,
                'address'      => ($addr = $e->addresses->first(fn($a) => $a->is_primary == 1) ?? $e->addresses->first()) 
                    ? implode(', ', array_filter([$addr->line_1, $addr->line_2, $addr->city, $addr->zipcode])) 
                    : null,
                'phone'        => $e->contacts->first(fn($c) => $c->is_primary == 1)?->mobile ?? $e->contacts->first()?->mobile ?? null,
                'email'        => $e->email ?? $e->contacts->first(fn($c) => $c->is_primary == 1)?->email ?? $e->contacts->first()?->email ?? null,
            ])->values();
        } else {
            // Normal users only see their assigned entities
            $entityAccess = EntityUser::with(['entity.addresses', 'entity.contacts', 'role'])
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
                    'is_suspended' => (int) ($group->first()->entity->is_suspended ?? 0),
                    'address'      => ($entity = $group->first()->entity) && ($addr = $entity->addresses->first(fn($a) => $a->is_primary == 1) ?? $entity->addresses->first())
                        ? implode(', ', array_filter([$addr->line_1, $addr->line_2, $addr->city, $addr->zipcode]))
                        : null,
                    'phone'        => ($entity = $group->first()->entity) 
                        ? ($entity->contacts->first(fn($c) => $c->is_primary == 1)?->mobile ?? $entity->contacts->first()?->mobile ?? null)
                        : null,
                    'email'        => ($entity = $group->first()->entity) 
                        ? ($entity->email ?? $entity->contacts->first(fn($c) => $c->is_primary == 1)?->email ?? $entity->contacts->first()?->email ?? null)
                        : null,
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
            
            $plantsQuery = Plant::where('entity_id', $entityId)->where('is_active', '!=', 0);
            
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
            
            // If there's exactly one plant available for this single entity and it is active (is_active == 1)
            if ($plants->count() === 1 && $plants->first()->is_active === 1) {
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

        $entity = Entity::find($entityId);
        if ($entity && $entity->is_suspended != 0 && !$user->isSystemAdmin()) {
            $reason = $entity->is_suspended == -1 
                ? 'This organization is suspended due to unpaid server charges or scheduled maintenance.'
                : 'This organization is currently inactive.';
            return response()->json(['error' => $reason], 403);
        }

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
            ->where('is_active', '!=', 0)
            ->select('id', 'name', 'code', 'is_main', 'is_active', 'email_address', 'mobile_number', 'logo_path')
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

        // Verify the plant belongs to the active entity and is not hidden
        $plant = Plant::where('id', $plantId)
            ->where('entity_id', $entityId)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$plant) {
            return response()->json(['error' => 'Invalid plant for the selected entity.'], 422);
        }

        // Restrict access if the plant is inactive (is_active == -1)
        if ($plant->is_active === -1) {
            return response()->json(['error' => 'Access Restricted: This facility is currently inactive.'], 403);
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

        // 2. Database Activity Log (mm_activity_log)
        app(AuditLogger::class)->log('STATUS_CHANGE', $entity, [
            'description' => "Organization [{$entity->legal_name}] status changed to [{$status}] by User [{$user->username}].",
            'old_values' => [
                'is_suspended' => $oldSuspendedState,
            ],
            'new_values' => [
                'is_suspended' => $entity->is_suspended,
                'status_label' => $status,
            ],
            'changed_fields' => ['is_suspended'],
            'metadata'   => [
                'old_state' => $oldSuspendedState,
                'new_state' => $entity->is_suspended,
                'user_ip'   => $request->ip(),
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'action' => $status,
            'is_suspended' => $entity->is_suspended,
        ]);
    }
}
