<?php
/*
Author: Cipher
Created: 2026-11-13 10:33:00 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Models\MobileModulePermission;
use App\Models\MobilePermission;
use App\Models\Plant;
use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MobileModulePermissionController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesModule;

    protected string $module = 'mobile_module_permission';

    public function page(Request $request)
    {
        $this->authorizeModule('view');
        abort_unless(MobileAccess::canManageUserAccess($request->user()), 403, 'Only administrators can manage mobile app permissions.');
        $plantId = $this->plantId($request);
        $plantName = Plant::find($plantId)->name;
        $entityId = (int) session('active_entity_id');
        $users = User::query()->where(function ($query) use ($plantId, $entityId) {
            $query->whereHas('entityUsers', fn ($membership) => $membership->where('plant_id', $plantId)->where('entity_id', $entityId))
                ->orWhereHas('personnel', fn ($personnel) => $personnel->where('plant_id', $plantId));
        })->when(!$request->user()->isSystemAdmin(), fn ($query) => $query->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN']))
            ->whereDoesntHave('entityUsers.role', fn ($roles) => $roles->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN'])))
            ->orderBy('username')->get(['id', 'username', 'email']);

        return Inertia::render('MobileModulePermissions/Index', ['users' => $users, 'plantId' => $plantId, 'plantName' => $plantName]);
    }

    public function index(Request $request, User $user)
    {
        $this->authorizeModule('view');
        $this->authorizeManager($request, $user);
        $plantId = $this->plantId($request);
        $catalog = MobilePermission::query()->orderBy('module')->orderBy('action')->get(['module', 'action'])
            ->groupBy('module')->map(fn ($permissions, $module) => ['module' => $module, 'actions' => $permissions->pluck('action')->unique()->values()->all()]);
        $assigned = MobileModulePermission::where('user_id', $user->id)->where('plant_id', $plantId)->get()->keyBy('module');

        return response()->json(['success' => true, 'data' => [
            'modules' => $catalog->map(fn ($item, $module) => $item + ['granted' => $assigned->get($module)?->actions ?? []])->values(),
        ]]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeModule('update');
        $this->authorizeManager($request, $user);
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.module' => ['required', 'string', 'max:100'],
            'permissions.*.actions' => ['sometimes', 'array'],
            'permissions.*.actions.*' => ['string', 'max:50'],
        ]);
        $plantId = $this->plantId($request);
        $validPermissions = MobilePermission::query()->get(['module', 'action'])->map(fn ($permission) => $permission->module.'.'.$permission->action)->all();

        DB::transaction(function () use ($data, $user, $plantId, $validPermissions, $request) {
            MobileModulePermission::where('user_id', $user->id)->where('plant_id', $plantId)->delete();
            foreach ($data['permissions'] as $permission) {
                $module = strtoupper($permission['module']);
                $actions = array_values(array_unique(array_map('strtoupper', $permission['actions'] ?? [])));
                foreach ($actions as $action) {
                    abort_unless(in_array($module.'.'.$action, $validPermissions, true), 422, "Unknown mobile permission: {$module}.{$action}");
                }
                if (!$actions) continue;
                MobileModulePermission::create(['user_id' => $user->id, 'plant_id' => $plantId, 'module' => $module,
                    'actions' => $actions, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Mobile app permissions updated.']);
    }

    public function destroy(Request $request, User $user, string $module)
    {
        $this->authorizeModule('delete');
        $this->authorizeManager($request, $user);
        $deleted = MobileModulePermission::where('user_id', $user->id)->where('plant_id', $this->plantId($request))
            ->where('module', strtoupper($module))->delete();
        abort_unless($deleted, 404, 'Mobile module permission not found.');

        return response()->json(['success' => true, 'message' => 'Mobile module permission removed.']);
    }

    private function authorizeManager(Request $request, User $target): void
    {
        abort_unless(MobileAccess::canManageUserAccess($request->user()), 403, 'Only administrators can manage mobile app permissions.');
        abort_if(!$request->user()->isSystemAdmin() && $target->isSystemAdmin(), 403, 'System administrator access can only be managed by a system administrator.');
    }

    private function plantId(Request $request): int
    {
        $plantId = (int) session('active_plant_id');
        abort_unless($plantId && Plant::whereKey($plantId)->where('entity_id', session('active_entity_id'))->exists(), 422, 'Select an active plant to manage mobile permissions.');
        return $plantId;
    }
}
