<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Plant;
use App\Models\Entity;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Http\Controllers\Concerns\AuthorizesModule;

class SiteController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'sites';
    public function index(Request $request)
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        $query = Site::query()
            ->where('plant_id', $plantId)
            ->with(['plant']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('plant_id')) {
            $query->where('plant_id', $request->plant_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');

        $sites = $query->orderBy($sortField, $sortDirection)
                      ->get();

        $allPatronIds = $sites->pluck('patron_id')->flatten()->unique()->filter()->toArray();
        $patronsMap = \App\Models\Patron::whereIn('id', $allPatronIds)->get()->keyBy('id');

        $sites->each(function($site) use ($patronsMap) {
            $sitePatronIds = is_array($site->patron_id) ? $site->patron_id : [];
            $sitePatrons = [];
            foreach ($sitePatronIds as $pid) {
                if (isset($patronsMap[$pid])) {
                    $sitePatrons[] = $patronsMap[$pid];
                }
            }
            $site->patrons_data = $sitePatrons;
            // Fallback for UI that expects site.patron.legal_name
            $site->patron = count($sitePatrons) > 0 ? $sitePatrons[0] : null; 
        });

        $user = Auth::user();
        $isPrivileged = $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin');

        if ($isPrivileged) {
            $plants = Plant::where('is_active', true)->select('id', 'name')->get();
        } else {
            $plants = Plant::where('is_active', true)->where('id', $plantId)->select('id', 'name')->get();
        }

        return Inertia::render('Sites/Index', [
            'sites'             => $sites,
            'filters'           => $request->only(['search', 'type', 'sort_field', 'sort_direction', 'per_page']),
            'plants'               => $plants,
            'siteTypes'         => ['loading', 'unloading'],
            'isPrivileged'      => $isPrivileged,
            'patrons'           => PatronsDropdown(['Customer']),
        ]);
    }

    public function store(StoreSiteRequest $request)
    {
        $this->authorizeModule('create');
        $payload = $request->validated();
        $user = Auth::user();
        $isPrivileged = $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Administrator');

        if (!$isPrivileged) {
            $payload['type'] = 'unloading';
            $payload['plant_id'] = session('active_plant_id');
        }

        $site = Site::create(array_merge($payload, [
            'status' => 'Active',
            'created_by' => $user->id
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Site created successfully.',
                'site' => $site
            ]);
        }

        return redirect()->back()->with('success', 'Site created successfully.');
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $this->authorizeModule('edit');
        $payload = $request->validated();
        $user = Auth::user();
        $isPrivileged = $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Administrator');

        if (!$isPrivileged) {
            $payload['type'] = 'unloading';
            $payload['plant_id'] = session('active_plant_id');
        }

        $site->update(array_merge($payload, [
          
            'updated_by' => $user->id
        ]));

        return redirect()->back()->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site)
    {
        $this->authorizeModule('delete');
        if ($site->is_in_use) {
            return redirect()->back()->with('error', 'Site cannot be deleted because it is in use by other records (Work Orders, Quotations, or Dispatches).');
        }

        $site->update(['deleted_by' => Auth::id()]);
        $site->delete();
        
        return redirect()->back()->with('success', 'Site deleted successfully.');
    }
}