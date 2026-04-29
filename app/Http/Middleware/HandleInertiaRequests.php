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
            // Auto-seed the session with the first entity if not already set
            if (!$activeEntityId) {
                $firstEntityUser = EntityUser::where('user_id', $user->id)->first();
                if ($firstEntityUser) {
                    $activeEntityId = $firstEntityUser->entity_id;
                    session(['active_entity_id' => $activeEntityId]);

                    // Also seed the first plant for this entity user
                    if ($firstEntityUser->plant_id && !session('active_plant_id')) {
                        session(['active_plant_id' => $firstEntityUser->plant_id]);
                    }
                }
            }

            $isSuperAdmin = $user->hasRole('Super Administrator') || $user->hasRole('Saas Owner');

            if ($isSuperAdmin) {
                // Super Admin sees every entity in the switcher dropdown
                $userEntities = Entity::all()->map(fn ($e) => [
                    'entity_id'   => $e->id,
                    'entity_name' => $e->legal_name,
                    'entity_logo' => $e->logo_file ?? null,
                    'role_id'     => 1,
                    'role_name'   => 'Super Administrator',
                    'is_active'   => $e->id === (int) $activeEntityId,
                ])->values();
            } else {
                // Normal users only see their assigned entities
                $userEntities = EntityUser::with(['entity', 'role'])
                    ->where('user_id', $user->id)
                    ->get()
                    ->map(fn ($eu) => [
                        'entity_id'   => $eu->entity_id,
                        'entity_name' => $eu->entity->legal_name ?? 'Unknown',
                        'entity_logo' => $eu->entity->logo_file ?? null,
                        'role_id'     => $eu->role_id ?? null,
                        'role_name'   => $eu->role->name ?? 'Unknown',
                        'is_active'   => $eu->entity_id === (int) $activeEntityId,
                    ])
                    ->values();
            }

            // Current active entity details
            if ($activeEntityId) {
                $activeEntity = $userEntities->firstWhere('entity_id', (int) $activeEntityId);
            }
        }

        $menus = [];
        if ($user) {
            $topNav = \App\Models\Menu::where('menutype', 1)
                ->where('published', true)
                ->orderBy('ordering')
                ->get();

            $sideNav = \App\Models\Menu::where('menutype', 2)
                ->where('published', true)
                ->orderBy('ordering')
                ->get()
                ->groupBy('parent_id');

            $menus = [
                'top_nav' => $topNav,
                'sidebar_nav' => $sideNav,
            ];
        }

        $activePlantId = session('active_plant_id');
        $activePlant   = null;
        $customSettings = [];
        if ($activePlantId) {
            $plant = Plant::find($activePlantId);
            if ($plant) {
                $activePlant = [
                    'plant_id'   => $plant->id,
                    'plant_name' => $plant->name,
                    'plant_code' => $plant->code,
                ];
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

        return [
            ...parent::share($request),
            'active_entity'    => $activeEntity,
            'active_plant'     => $activePlant,
            'active_plant_id'  => $activePlantId,
            'user_entities'    => $userEntities,
            'user_role'        => $user?->getRoleNames()->first(),
            'user_code'        => $user?->getRoleCode()->first(),
            'app_env'          => app()->environment(),
            'user_permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            'menus'            => $menus,
            'custom_settings'  => $customSettings,
            'plants_count'     => $plantsCount,
        ];
    }
}
