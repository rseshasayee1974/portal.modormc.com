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
use Spatie\Permission\Models\Role;
use App\Models\Plant;
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
        $isSuperUser = $user && method_exists($user, 'hasRole') && (
            $user->hasRole('Saas Owner') || 
            $user->hasRole('Platform Admin') || 
            $user->hasRole('Super Admin')
        );

        $query = User::with(['entityUsers.entity', 'entityUsers.plant', 'entityUsers.role']);

        if ($isSuperUser) {
            $query->withTrashed();
        } else {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('code', 'SAAS_OWNER');
            });

            $activePlantId = app(\App\Services\PlantContextService::class)->plantId();
            if ($activePlantId) {
                $query->whereHas('entityUsers', function ($q) use ($activePlantId) {
                    $q->where('plant_id', $activePlantId);
                });
            } else {
                $activeEntityId = app(\App\Services\PlantContextService::class)->entityId();
                if ($activeEntityId) {
                    $query->whereHas('entityUsers', function ($q) use ($activeEntityId) {
                        $q->where('entity_id', $activeEntityId);
                    });
                }
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
        
        // Load dropdown collections for Form
        if ($isSuperUser) {
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
            $userGroups = Role::all()->map(function ($group) {
                return [
                    'value' => $group->id,
                    'label' => $group->name,
                ];
            })->values();
        } else {
            $spatieLevel = $user->roles->min('level');
            $entityLevel = $user->entityUsers()->with('role')->get()->min('role.level');
            $levels = array_filter([$spatieLevel, $entityLevel], fn($v) => !is_null($v));
            $userLevel = empty($levels) ? 100 : min($levels);
            
            $userGroups = Role::where('level', '<=', $userLevel)->get()->map(function ($group) {
                return [
                    'value' => $group->id,
                    'label' => $group->name,
                ];
            })->values();
        }

        $plantsQuery = Plant::where('is_active', 1);
        $activePlantId = app(\App\Services\PlantContextService::class)->plantId();
        if ($activePlantId && !$isSuperUser) {
            $plantsQuery->where('id', $activePlantId);
        }
        $plants = $plantsQuery->get()->map(fn($p) => [
            'value' => $p->id, 
            'label' => $p->name,
            'entity_id' => $p->entity_id
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            $currentUser = User::with(['entityUsers.entity', 'entityUsers.plant', 'entityUsers.role'])
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
            'users' => $users,
            'entities' => $entities,
            'plants' => $plants,
            'userGroups' => $userGroups,
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
        $user->load(['entityUsers.entity', 'entityUsers.role', 'entityUsers.plant']);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorizeModule('edit');

        $data = $request->validated();

        // Empty string = user removed the photo; null the key so model deletes the old file
        if (array_key_exists('profile_photo_path', $data) && $data['profile_photo_path'] === '') {
            $data['profile_photo_path'] = null;
        }

        $user->updateWithRelations($data);

        return response()->json(['message' => 'User updated successfully.', 'user' => $user->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorizeModule('delete');
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
        if (!$user || !($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin'))) {
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
        if (!$user || !($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin'))) {
            abort(403, 'Unauthorized.');
        }

        $targetUser = User::withTrashed()->findOrFail($id);
        if ($targetUser->profile_photo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($targetUser->profile_photo_path);
        }

        $targetUser->entityUsers()->forceDelete(); // Clean relationships permanently
        $targetUser->forceDelete();

        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'User permanently deleted.']);
        }

        return redirect()->back()->with('success', 'User permanently deleted.');
    }
}