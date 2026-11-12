<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\Plant;
use App\Services\PlantContextService;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class UserController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'users';
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeModule('menu');
        /** @var User $user */
        $user = $request->user();
        $isSuperUser = $user && $user->hasFullAccess();

        $context = app(PlantContextService::class);
        $activePlantId = $context->plantId();
        $activeEntityId = $context->entityId();
        $memberships = $this->membershipRelations($activePlantId);
        $query = User::with($memberships)
            ->with(['personnel' => fn ($q) => $q->withoutGlobalScope('plant_id')->where('plant_id', $activePlantId)])
            ->when($activePlantId, fn ($q) => $q->whereHas('entityUsers', function ($m) use ($activePlantId, $isSuperUser) {
                $m->where('plant_id', $activePlantId);
                if ($isSuperUser) {
                    // Deleted accounts also have soft-deleted membership rows.
                    $m->withTrashed()->where(fn ($active) => $active->whereNull('mm_entity_users.deleted_at')
                        ->orWhereNotNull('mm_users.deleted_at'));
                }
            }))
            ->where('id', '!=', $user->id);

        if ($isSuperUser) {
            $query->withTrashed();
        } else {
            $spatieLevel = $user->roles->max('level');
            $entityLevel = $user->entityUsers()->with('role')->get()->max('role.level');
            $levels = array_filter([$spatieLevel, $entityLevel], fn($v) => !is_null($v));
            $userLevel = empty($levels) ? 0 : max($levels);

            // Exclude super users and any users having roles higher than the logged-in user
            $query->whereDoesntHave('roles', function ($q) use ($userLevel) {
                $q->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN'])
                  ->orWhere('level', '>', $userLevel);
            });

            $query->whereDoesntHave('entityUsers.role', function ($q) use ($userLevel) {
                $q->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN'])
                  ->orWhere('level', '>', $userLevel);
            });

            if (!$activePlantId && $activeEntityId) {
                $query->whereHas('entityUsers', fn ($q) => $q->where('entity_id', $activeEntityId));
            }
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        $dropdownData = null;
        $dropdowns = function () use ($isSuperUser, $user, $activePlantId, $activeEntityId, &$dropdownData) {
            if ($dropdownData !== null) return $dropdownData;
            // Load dropdown collections for Form
            if ($activePlantId) {
                $availableEntities = Entity::whereKey($activeEntityId)->get();
            } elseif ($isSuperUser) {
                $availableEntities = Entity::all();
            } else {
                // Get entities from EntityUser mapping
                $availableEntities = $user->entityUsers()->with('entity')->get()->pluck('entity')->filter(function ($entity) {
                    return $entity && $entity->is_active == 1;
                });
            }

            $entities = $availableEntities->map(function ($entity) {
                return [
                    'value' => $entity->id,
                    'label' => $entity->legal_name . ($entity->alias ? " ({$entity->alias})" : ''),
                ];
            })->values();

            if ($isSuperUser) {
                $userGroups = Role::active()->get()->map(function ($group) {
                    return [
                        'value' => $group->id,
                        'label' => $group->name,
                    ];
                })->values();
            } else {
                $spatieLevel = $user->roles->max('level');
                $entityLevel = $user->entityUsers()->with('role')->get()->max('role.level');
                $levels = array_filter([$spatieLevel, $entityLevel], fn($v) => !is_null($v));
                $userLevel = empty($levels) ? 0 : max($levels);

                $userGroups = Role::active()->where('level', '<', $userLevel)
                    ->whereNotIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'])
                    ->get()
                    ->map(function ($group) {
                        return [
                            'value' => $group->id,
                            'label' => $group->name,
                        ];
                    })->values();
            }

            $plantsQuery = Plant::where('is_active', 1);
            if ($activePlantId) {
                $plantsQuery->where('id', $activePlantId);
            }
            $plants = $plantsQuery->get()->map(fn($p) => [
                'value' => $p->id,
                'label' => $p->name,
                'entity_id' => $p->entity_id
            ]);
            return $dropdownData = compact('entities', 'plants', 'userGroups');
        };

        if ($request->wantsJson() || $request->is('api/*')) {
            extract($dropdowns());
            $currentUser = User::with($memberships)
                ->find($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $currentUser,
                    'entities' => $entities,
                    'plants' => $plants,
                    'userGroups' => $userGroups,
                ]
            ]);
        }

        return Inertia::render('Users/Index', [
            'can_manage_user_links' => UserPersonnelLinkController::canManage($user),
            'mobile_link_users' => UserPersonnelLinkController::canManage($user)
                ? UserPersonnelLinkController::eligibleUsers()->orderBy('username')->get(['id', 'username', 'email', 'is_otp_enabled']) : [],
            'unlinkedPersonnel' => UserPersonnelLinkController::canManage($user)
                ? \App\Models\Personnel::where('plant_id', session('active_plant_id'))->where('entity_id', session('active_entity_id'))
                    ->whereNull('user_id')->whereRaw('LOWER(status) = ?', ['active'])->orderBy('first_name')
                    ->get(['id', 'user_id', 'first_name', 'last_name', 'employee_code', 'email', 'mobile', 'plant_id', 'entity_id']) : [],
            'users' => $users,
            'entities' => fn () => $dropdowns()['entities'],
            'plants' => fn () => $dropdowns()['plants'],
            'userGroups' => fn () => $dropdowns()['userGroups'],
            'canManageMobileAccess' => UserPersonnelLinkController::canManage($user),
            'filters' => $request->only('search')
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorizeModule('create');

        $data = $request->validated();
        if (!empty($data['personnel_id'])) {
            abort_unless(UserPersonnelLinkController::canManage($request->user()), 403, 'Only administrators can link personnel.');
            $hasMembership = collect($data['entity_users'] ?? [])->contains(fn ($membership) =>
                (int) ($membership['entity_id'] ?? 0) === (int) session('active_entity_id')
                && (empty($membership['plant_id']) || (int) $membership['plant_id'] === (int) session('active_plant_id')));
            if (!$hasMembership) throw \Illuminate\Validation\ValidationException::withMessages([
                'entity_users' => 'Assign the login account to the personnel’s entity and plant.',
            ]);
        }
        if (!empty($data['is_otp_enabled'])) {
            abort_unless(UserPersonnelLinkController::canManage($request->user()), 403, 'Only administrators can manage mobile app access.');
        }

        if (array_key_exists('profile_photo_path', $data) && $data['profile_photo_path'] === '') {
            unset($data['profile_photo_path']);
        }

        // Handle optional password
        $plainPassword = $data['password'] ?? null;
        if (empty($plainPassword)) {
            // Generate a strong random 8-character password
            $plainPassword = \Illuminate\Support\Str::random(8);
            $data['password'] = $plainPassword;
        }

        $user = User::saveWithRelations($data);

        // Send email with credentials
        try {
            $user->notify(new \App\Notifications\UserCredentialsNotification($plainPassword));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send credentials email: ' . $e->getMessage());
        }

        return response()->json(['message' => 'User created successfully.', 'user' => $user]);
    }

    /**
     * Display the specified resource relationships dynamically (Ajax Request).
     */
    public function show(User $user)
    {
        $this->authorizeModule('show');
        $this->ensureUserAccess($user, auth()->user());
        $user->load($this->membershipRelations(app(PlantContextService::class)->plantId()));
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorizeModule('edit');
        $this->ensureUserAccess($user, $request->user());

        $data = $request->validated();
        if (array_key_exists('is_otp_enabled', $data) && (bool)$data['is_otp_enabled'] !== (bool)$user->is_otp_enabled) {
            abort_unless(UserPersonnelLinkController::canManage($request->user()), 403, 'Only administrators can manage mobile app access.');
            abort_if(!$request->user()->isSystemAdmin() && $user->isSystemAdmin(), 403, 'System administrator access can only be managed by a system administrator.');
        }

        // Empty string = user removed the photo; null the key so model deletes the old file
        if (array_key_exists('profile_photo_path', $data) && $data['profile_photo_path'] === '') {
            $data['profile_photo_path'] = null;
        }

        $context = app(PlantContextService::class);
        $plantId = $context->plantId();
        if ($plantId) {
            foreach ($data['entity_users'] ?? [] as $membership) {
                if ((int) ($membership['plant_id'] ?? 0) !== $plantId || (int) $membership['entity_id'] !== $context->entityId()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['entity_users' => 'Select the current entity and plant.']);
                }
            }
        }
        $user->updateWithRelations($data, $plantId);

        return response()->json(['message' => 'User updated successfully.', 'user' => $user->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorizeModule('delete');
        $this->ensureUserAccess($user, auth()->user());
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->entityUsers()->delete(); // Clean relationships
        $user->delete();

        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
        }

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    /**
     * Generate WhatsApp verification link for the user.
     */
    public function whatsappVerificationUrl(User $user)
    {
        $this->authorizeModule('edit');

        if (empty($user->mobile)) {
            return response()->json([
                'message' => 'User does not have a mobile number saved.'
            ], 422);
        }

        $mobile = preg_replace('/[^0-9]/', '', $user->mobile);
        if (strlen($mobile) === 10) {
            $mobile = '91' . $mobile;
        }

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $message = "*onemodo.com*\n"
            . "Hello *" . $user->username . "*!\n"
            . "Please click the link below to verify your email address.\n\n"
            . "*Verify Email Address*\n"
            . $signedUrl . "\n\n"
            . "If you did not create an account, no further action is required.\n\n"
            . "Regards,\n"
            . "*Modormc*";

        $waUrl = "https://wa.me/" . $mobile . "?text=" . urlencode($message);

        return response()->json([
            'url' => $waUrl
        ]);
    }

    public function restore($id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasFullAccess()) {
            abort(403, 'Unauthorized.');
        }

        $targetUser = User::onlyTrashed()->findOrFail($id);
        $targetUser->restore();

        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'User restored successfully.']);
        }

        return redirect()->back()->with('success', 'User restored successfully.');
    }

    public function forceDelete($id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasFullAccess()) {
            abort(403, 'Unauthorized.');
        }

        $targetUser = User::withTrashed()->findOrFail($id);
        if ($targetUser->profile_photo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($targetUser->profile_photo_path);
        }

        $targetUser->entityUsers()->delete(); // Soft delete relationships
        $targetUser->delete(); // Soft delete user

        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
        }

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    private function membershipRelations(?int $plantId): array
    {
        return ['entityUsers' => fn ($q) => $q->when($plantId, fn ($m) => $m->where('plant_id', $plantId))
            ->with(['entity', 'plant', 'role'])];
    }

    protected function ensureUserAccess(User $targetUser, ?User $currentUser): void
    {
        if (!$currentUser) {
            abort(401, 'Unauthenticated.');
        }

        $isSuperUser = $currentUser->hasFullAccess();

        $activePlantId = app(PlantContextService::class)->plantId();
        if ($activePlantId && !$targetUser->entityUsers()->where('plant_id', $activePlantId)->exists()) {
            abort(403, 'Unauthorized access to user outside current plant.');
        }
        if ($isSuperUser) {
            return;
        }

        $spatieLevel = $currentUser->roles->max('level');
        $entityLevel = $currentUser->entityUsers()->with('role')->get()->max('role.level');
        $levels = array_filter([$spatieLevel, $entityLevel], fn($v) => !is_null($v));
        $userLevel = empty($levels) ? 0 : max($levels);

        $targetSpatie = $targetUser->roles->max('level');
        $targetEntity = $targetUser->entityUsers()->with('role')->get()->max('role.level');
        $targetLevels = array_filter([$targetSpatie, $targetEntity], fn($v) => !is_null($v));
        $targetLevel = empty($targetLevels) ? 0 : max($targetLevels);

        $hasSuperRole = $targetUser->roles()->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN'])->exists()
            || $targetUser->entityUsers()->whereHas('role', fn($q) => $q->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN']))->exists();

        if ($hasSuperRole || $targetLevel > $userLevel) {
            abort(403, 'Unauthorized access to user with higher privileges.');
        }

    }
}
