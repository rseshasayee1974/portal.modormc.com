<?php
/*
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\MobilePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MobilePermissionController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'mobile_permission';

    public function index(Request $request)
    {
        $this->authorizeModule('view');
        $permissions = MobilePermission::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.trim($request->string('search')).'%'))
            ->orderBy('module')->orderBy('action')->paginate(30)->withQueryString();

        return Inertia::render('MobilePermissions/Index', ['permissions' => $permissions, 'filters' => $request->only('search')]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $data = $this->validated($request);
        MobilePermission::create($this->attributes($data));

        return back()->with('success', 'Mobile permission created.');
    }

    public function update(Request $request, MobilePermission $mobilePermission)
    {
        $this->authorizeModule('update');
        $data = $this->validated($request, $mobilePermission->id);
        $mobilePermission->update($this->attributes($data));

        return back()->with('success', 'Mobile permission updated.');
    }

    public function destroy(MobilePermission $mobilePermission)
    {
        $this->authorizeModule('delete');
        $mobilePermission->delete();

        return back()->with('success', 'Mobile permission removed.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['name' => strtoupper(trim((string) $request->input('name')))]);
        return $request->validate([
            'name' => ['required', 'string', 'max:150', 'regex:/^[A-Z0-9_]+\.[A-Z0-9_]+$/', Rule::unique('mm_mobile_permissions', 'name')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function attributes(array $data): array
    {
        [$module, $action] = explode('.', $data['name'], 2);
        return [
            'name' => $data['name'], 'module' => $module, 'action' => $action,
            'description' => $data['description'] ?? Str::title(strtolower(str_replace('_', ' ', $action)).' '.strtolower(str_replace('_', ' ', $module))),
        ];
    }
}
