<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\EntityUser;
use App\Models\Entity;
use App\Models\Plant;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeEntityId = session('active_entity_id');
        $activeEntity = null;
        $userEntities = [];

        if ($user) {
            $isSuperAdmin = $user->hasRole('Super Administrator') || $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin');

            // Auto-seed session for system admins if not already set
            if ($isSuperAdmin && !session('active_entity_id')) {
                $firstEntityUser = EntityUser::where('user_id', $user->id)->first();
                if ($firstEntityUser) {
                    session(['active_entity_id' => $firstEntityUser->entity_id]);
                    if ($firstEntityUser->plant_id && !session('active_plant_id')) {
                        session(['active_plant_id' => $firstEntityUser->plant_id]);
                    }
                } else {
                    // Fallback for admins with no specific EntityUser record
                    $defaultPlant = Plant::first();
                    if ($defaultPlant) {
                        session(['active_entity_id' => $defaultPlant->entity_id]);
                        session(['active_plant_id'  => $defaultPlant->id]);
                    }
                }
                // Refresh local variable after seeding
                $activeEntityId = session('active_entity_id');
            }

            if ($isSuperAdmin) {
                // Super Admin sees every entity in the switcher dropdown
                $userEntities = Entity::all()->map(fn ($e) => [
                    'entity_id'    => $e->id,
                    'entity_name'  => $e->legal_name,
                    'entity_logo'  => $e->logo_file ?? null,
                    'role_id'      => 1,
                    'role_name'    => 'Super Administrator',
                    'is_active'    => $e->id === (int) $activeEntityId,
                    'is_suspended' => (int) $e->is_suspended,
                ])->values();
            } else {
                // Normal users only see their assigned entities
                $userEntities = EntityUser::with(['entity', 'role'])
                    ->where('user_id', $user->id)
                    ->get()
                    ->map(fn ($eu) => [
                        'entity_id'    => $eu->entity_id,
                        'entity_name'  => $eu->entity->legal_name ?? 'Unknown',
                        'entity_logo'  => $eu->entity->logo_file ?? null,
                        'role_id'      => $eu->role_id ?? null,
                        'role_name'    => $eu->role->name ?? 'Unknown',
                        'is_active'    => $eu->entity_id === (int) $activeEntityId,
                        'is_suspended' => (int) ($eu->entity->is_suspended ?? 0),
                    ])
                    ->values();
            }

            // Current active entity details
            if ($activeEntityId) {
                $entityObj = \App\Models\Entity::find($activeEntityId);
                if ($entityObj && $entityObj->is_suspended != 0 && !$user->isSystemAdmin()) {
                    session()->forget(['active_entity_id', 'active_plant_id']);
                    $activeEntityId = null;
                } else {
                    $activeEntity = $userEntities->firstWhere('entity_id', (int) $activeEntityId);
                }
            }
        }

        $menus = [];

        $activePlantId = session('active_plant_id');
        $activePlant   = null;
        $customSettings = [];
        if ($activePlantId) {
            $plant = Plant::find($activePlantId);
            if ($plant && $plant->is_active === 1) {
                $mixerCapacity = (float)($plant->mixer_capacity ?: 1.25);
                $activePlant = [
                    'plant_id'       => $plant->id,
                    'plant_name'     => $plant->name,
                    'plant_code'     => $plant->code,
                    'plant_logo'     => $plant->logo_path ? "/storage/{$plant->logo_path}" : null,
                    'mixer_capacity' => $mixerCapacity,
                ];
                session(['mixer_capacity' => $mixerCapacity]);
            } else {
                session()->forget(['active_plant_id', 'mixer_capacity']);
                $activePlantId = null;
            }
            $customSettings['batching'] = \App\Models\CustomSetting::getForModule($activePlantId, 'batching');
            $customSettings['orders'] = \App\Models\CustomSetting::getForModule($activePlantId, 'orders');
        }

        $plantsCount = 0;
        if ($user && $activeEntityId) {
            if ($user->hasRole('Platform Admin') || $user->hasRole('Saas Owner')) {
                $plantsCount = Plant::where('entity_id', $activeEntityId)->where('is_active', true)->count();
            } else {
                $entityAssignments = EntityUser::where('user_id', $user->id)
                    ->where('entity_id', $activeEntityId) 
                    ->get(['plant_id']);

                $hasEntityWideAccess = $entityAssignments->contains(fn ($row) => $row->plant_id === null);

                if ($hasEntityWideAccess) {
                    $plantsCount = Plant::where('entity_id', $activeEntityId)->where('is_active', true)->count();
                } else {
                    $plantsCount = $entityAssignments->pluck('plant_id')->filter()->unique()->count();
                }
            }
        }

        // Determine Tenant-Specific Role and Permissions or user - specific roles of the logged in user
        $activeRole = null;
        if ($activeEntity && isset($activeEntity['role_id'])) {
            $activeRole = \App\Models\Role::with('permissions')->find($activeEntity['role_id']);
        }

        $tenantRoleName = $activeRole ? $activeRole->name : $user?->getRoleNames()->first();
        $tenantRoleCode = $activeRole ? $activeRole->code : $user?->getRoleCode()->first();
        
        $tenantPermissions = collect();
        if ($activeRole) {
            $tenantPermissions = $activeRole->permissions->pluck('name');
            if ($user) {
                $tenantPermissions = $tenantPermissions->merge($user->getDirectPermissions()->pluck('name'))->unique()->values();
            }
        } else {
            $tenantPermissions = $user ? $user->getAllPermissions()->pluck('name') : collect();
        }

        if ($user) {
            $isSuper = $user->isSystemAdmin();

            $isMasterMenu = function ($menu) {
                // If it is the Master menu or a child of it
                if ($menu->id === 2 || $menu->parent_id === 2) {
                    return true;
                }
                
                if ($menu->permission_name) {
                    $prefix = strtolower(explode('.', $menu->permission_name)[0]);
                    $masterModules = [
                        'master',
                        'address_type',
                        'bank_account_type',
                        'contact_type',
                        'country',
                        'currency',
                        'entity_type',
                        'invoice_status',
                        'payment_status',
                        'payment_method',
                        'plan',
                        'subscription_status',
                        'state_code',
                        'menu',
                        'role',
                        'permission'
                    ];
                    if (in_array($prefix, $masterModules)) {
                        return true;
                    }
                }
                
                return false;
            };

            $isSassOwnerOnly = $user->hasAnyRole(['Saas Owner', 'Platform Admin']);

            $sideNav = \App\Models\Menu::where('menutype', 2)
                ->where('published', true)
                ->orderBy('ordering')
                ->get()
                ->filter(function ($item) use ($isSuper, $tenantPermissions, $isMasterMenu, $isSassOwnerOnly) {
                    if ($isMasterMenu($item)) {
                        return $isSassOwnerOnly;
                    }
                    if ($isSuper) return true;
                    if (!$item->permission_name) return true;
                    return $tenantPermissions->contains(fn($p) => strtolower($p) === strtolower($item->permission_name));
                })
                ->values()
                ->groupBy('parent_id');

            $topNav = \App\Models\Menu::where('menutype', 1)
                ->where('published', true)
                ->orderBy('ordering')
                ->get()
                ->filter(function ($item) use ($isSuper, $tenantPermissions, $isMasterMenu, $isSassOwnerOnly, $sideNav) {
                    if ($isMasterMenu($item)) {
                        return $isSassOwnerOnly;
                    }
                    if ($isSuper) return true;

                    $hasDirectPerm = $item->permission_name
                        ? $tenantPermissions->contains(fn($p) => strtolower($p) === strtolower($item->permission_name))
                        : false;

                    $hasChildrenInDb = \App\Models\Menu::where('menutype', 2)->where('parent_id', $item->id)->count() > 0;
                    $hasChildSubNav = isset($sideNav[$item->id]) && $sideNav[$item->id]->isNotEmpty();

                    if ($hasChildrenInDb) {
                        if (!$hasChildSubNav) {
                            return false;
                        }

                        $isLinkPermitted = $sideNav[$item->id]->contains(fn($child) => $child->link === $item->link);
                        if (!$isLinkPermitted) {
                            $item->link = $sideNav[$item->id]->first()->link;
                        }
                        return true;
                    }

                    if ($item->permission_name) {
                        return $hasDirectPerm;
                    }

                    return true;
                })
                ->values();

            $menus = [
                'top_nav' => $topNav,
                'sidebar_nav' => $sideNav,
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'default_plant_id' => $user->default_plant_id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'is_active' => $user->is_active,
                    'last_visit_page' => $user->last_visit_page,
                    'mobile' => $user->mobile,
                    'username' => $user->username,
                    'profile_photo_url' => $user->profile_photo_url,
                    'roles' => $user->roles->map(fn($role) => [
                        'id' => $role->id,
                        'code' => $role->code,
                        'name' => $role->name,
                    ])->toArray(),
                ] : null,
            ],
            'active_entity'    => $activeEntity,
            'active_plant'     => $activePlant,
            'active_plant_id'  => $activePlantId,
            'mixer_capacity'   => fn () => session('mixer_capacity', $activePlant['mixer_capacity'] ?? 1.25),
            'user_entities'    => $userEntities,
            'user_role'        => $tenantRoleName,
            'user_code'        => $tenantRoleCode,
            'app_env'          => app()->environment(),
            'user_permissions' => $tenantPermissions,
            'menus'            => $menus,
            'custom_settings'  => $customSettings,
            'plants_count'     => $plantsCount,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'new_batch_id' => fn () => $request->session()->get('new_batch_id'),
                'dispatched_batch_id' => fn () => $request->session()->get('dispatched_batch_id'),
            ],
        ];
    }
}