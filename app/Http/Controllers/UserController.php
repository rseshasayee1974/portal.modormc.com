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
        $query = User::with(['entityUsers.entity', 'entityUsers.plant', 'entityUsers.role'])
            ->whereDoesntHave('roles', function ($q) {
                $q->where('code', 'SAAS_OWNER');
            });

        $activeEntityId = session('active_entity_id');
        if ($activeEntityId) {
            $query->whereHas('entityUsers', function ($q) use ($activeEntityId) {
                $q->where('entity_id', $activeEntityId);
            });
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
        /** @var User $user */
        $user = $request->user();
 
        if ($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin')) {
            $availableEntities = Entity::where('is_active', 1)->get();
          
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

        $spatieLevel = $user->roles->min('level');
        $entityLevel = $user->entityUsers()->with('role')->get()->min('role.level');
        $levels = array_filter([$spatieLevel, $entityLevel], fn($v) => !is_null($v));
        $userLevel = empty($levels) ? 999 : min($levels);
        
        $userGroups = Role::where('level', '<', $userLevel)->get()->map(function ($group) {
            return [
                'value' => $group->id,
                'label' => $group->name,
            ];
        })->values();
        // dd($userGroups,$levels,$userLevel,$entityLevel,$spatieLevel);
        $plants = Plant::where('is_active', 1)->get()->map(fn($p) => [
            'value' => $p->id, 
            'label' => $p->name,
            'entity_id' => $p->entity_id
        ]);
// dd($query,$plants);
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
        // dd(Auth::user(),session('entity_id'),$request->all());
        $this->authorizeModule('create');

        $data = $request->validated();
// dd($data);
        // Drop empty-string photo so model doesn't store '' as a path
        if (array_key_exists('profile_photo_path', $data) && $data['profile_photo_path'] === '') {
            unset($data['profile_photo_path']);
        }

        $user = User::saveWithRelations($data);

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

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
