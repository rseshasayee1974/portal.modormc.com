<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class RoleController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'roles';
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeModule('menu');
        $query = Role::query();
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        $roles = $query->paginate(15)->withQueryString();

        // Dynamically extract all available permissions and group them by the prefix (module)
        // e.g., 'users.create', 'users.edit' becomes ['users' => ['create', 'edit']]
        $allPermissions = Permission::orderBy('name')->get();
        $groupedPermissions = [];
        
        foreach ($allPermissions as $perm) {
            $parts = explode('.', $perm->name);
            $module = count($parts) > 1 ? $parts[0] : 'general';
            $action = count($parts) > 1 ? implode('.', array_slice($parts, 1)) : $perm->name;
            
            if (!isset($groupedPermissions[$module])) {
                $groupedPermissions[$module] = [];
            }
            $groupedPermissions[$module][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'action_label' => ucfirst($action)
            ];
        }

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
            'filters' => $request->only('search')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(StoreRoleRequest $request)
    {
        $this->authorizeModule('create');
        $role = Role::saveWithRelations($request->validated());
        return response()->json(['message' => 'Role created successfully.', 'role' => $role]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        // Return explicit explicitly bound permissions to populate the Edit modal instantly.
        return response()->json([
            'role' => $role,
            'permissions' => $role->permissions->pluck('name')->toArray()
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorizeModule('edit');
        $role->updateWithRelations($request->validated());
        return response()->json(['message' => 'Role updated successfully.', 'role' => $role]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $this->authorizeModule('delete');
        
        if ($role->is_system) {
            return response()->json(['message' => 'System protected roles cannot be deleted.'], 403);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
