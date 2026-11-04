<?php
/*
Author: Cipher
Created: 2026-11-04 12:25:19 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Models\{EntityUser, Personnel, PersonnelUserRel, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserPersonnelLinkController extends Controller
{
    public static function canManage(?User $user): bool
    {
        if (!$user) return false;
        if ($user->isSystemAdmin()) return true;
        if (!session('active_entity_id') || !session('active_plant_id')) return false;
        $role = EntityUser::forContext($user->id, (int) session('active_entity_id'), session('active_plant_id'))?->role;
        return strtoupper((string) $role?->code) === 'ADMINISTRATOR' || ($role?->grantsFullAccess() ?? false);
    }

    public static function eligibleUsers()
    {
        return User::query()->where('is_active', true)
            ->whereHas('entityUsers', fn ($q) => $q->where('entity_id', session('active_entity_id'))
                ->where(fn ($scope) => $scope->whereNull('plant_id')->orWhere('plant_id', session('active_plant_id'))))
            ->when(!auth()->user()?->isSystemAdmin(), fn ($q) => $q->whereDoesntHave('roles', fn ($r) =>
                $r->whereIn('code', ['SAAS_OWNER', 'SUPER_ADMIN', 'PLATFORM_ADMIN'])));
    }

    public function update(Request $request, int $personnel)
    {
        abort_unless(self::canManage($request->user()), 403, 'Only administrators can link mobile users.');
        abort_unless(session('active_plant_id') && session('active_entity_id'), 422, 'Select a plant first.');
        $data = $request->validate(['user_id' => 'present|nullable|integer', 'enabled' => 'required|boolean']);
        if ($data['enabled'] && !$data['user_id']) {
            throw ValidationException::withMessages(['user_id' => 'Select a user to enable mobile app access.']);
        }

        return DB::transaction(function () use ($personnel, $data) {
            // Serialize links per plant, including legacy personnel.user_id mappings.
            \App\Models\Plant::whereKey(session('active_plant_id'))->lockForUpdate()->firstOrFail();
            $person = Personnel::withoutGlobalScope('plant_id')->where('plant_id', session('active_plant_id'))
                ->where('entity_id', session('active_entity_id'))->lockForUpdate()->findOrFail($personnel);
            $user = null;
            if ($data['user_id']) {
                $user = self::eligibleUsers()->lockForUpdate()->find($data['user_id']);
                if (!$user) throw ValidationException::withMessages(['user_id' => 'Select an active user belonging to this plant.']);
                $duplicate = Personnel::withoutGlobalScope('plant_id')->where('plant_id', $person->plant_id)
                    ->where('user_id', $user->id)->whereKeyNot($person->id)->exists();
                if ($duplicate) throw ValidationException::withMessages(['user_id' => 'This user is already linked to another personnel record in this plant.']);
            }
            $oldUserId = $person->user_id;
            if ((int) $oldUserId !== (int) $user?->id) {
                foreach (array_filter(array_unique([$oldUserId, $user?->id])) as $id) {
                    $linkedUser = User::lockForUpdate()->find($id);
                    if (!$linkedUser) continue;
                    $linkedUser->forceFill(['mobile_access_revision' => (int) $linkedUser->mobile_access_revision + 1])->save();
                    $linkedUser->tokens()->where('name', 'like', 'sales-mobile:%')->delete();
                }
            }
            $person->update(['user_id' => $user?->id]);
            $activeLinks = PersonnelUserRel::where('personnel_id', $person->id)->lockForUpdate()->get();
            foreach ($activeLinks as $link) {
                if (!$user || (int) $link->user_id !== (int) $user->id) $link->delete();
            }
            if ($user) {
                $link = PersonnelUserRel::withTrashed()->where('personnel_id', $person->id)
                    ->where('user_id', $user->id)->lockForUpdate()->latest('id')->first();
                if ($link?->trashed()) {
                    $link->restore();
                } elseif (!$link) {
                    PersonnelUserRel::create(['user_id' => $user->id, 'personnel_id' => $person->id, 'plant_id' => $person->plant_id]);
                }
                $user->refresh()->forceFill(['is_otp_enabled' => (bool) $data['enabled']])->save();
            }
            return response()->json(['success' => true, 'message' => 'User link and mobile access saved.',
                'data' => ['user_id' => $user?->id, 'enabled' => (bool) $user?->is_otp_enabled]]);
        });
    }

}
