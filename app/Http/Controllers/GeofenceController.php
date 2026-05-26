<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeofenceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'geofence';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        $geofences = Geofence::where('plant_id', $activePlantId)
            ->latest()
            ->get();

        return Inertia::render('Geofences/Index', [
            'geofences' => $geofences,
            'shapes' => [
                ['label' => 'Circle', 'value' => 'circle'],
                ['label' => 'Polygon', 'value' => 'polygon']
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:250',
            'description' => 'nullable|string',
            'shape' => 'required|string|in:circle,polygon',
            'coordinates' => 'required|array',
            'is_active' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            $activePlantId = session('active_plant_id');
            $userId = auth()->id();

            Geofence::create([
                'plant_id' => $activePlantId,
                'name' => $validated['name'],
                'description' => $validated['description'],
                'shape' => $validated['shape'],
                'coordinates' => $validated['coordinates'],
                'is_active' => $validated['is_active'],
                'created_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Geofence created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Geofence store error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to save geofence: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Geofence $geofence)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:250',
            'description' => 'nullable|string',
            'shape' => 'required|string|in:circle,polygon',
            'coordinates' => 'required|array',
            'is_active' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            $userId = auth()->id();

            $geofence->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'shape' => $validated['shape'],
                'coordinates' => $validated['coordinates'],
                'is_active' => $validated['is_active'],
                'updated_by' => $userId
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Geofence updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Geofence update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update geofence: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Geofence $geofence)
    {
        $this->authorizeModule('delete');

        DB::beginTransaction();
        try {
            $userId = auth()->id();

            $geofence->update(['deleted_by' => $userId]);
            $geofence->delete();

            DB::commit();
            return redirect()->back()->with('success', 'Geofence deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Geofence destroy error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete geofence.']);
        }
    }
}
