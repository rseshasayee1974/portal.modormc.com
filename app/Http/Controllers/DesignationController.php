<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class DesignationController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'designation';

    public function index()
    {
        $this->authorizeModule('menu');

        return Inertia::render('Designations/Index', [
            'activePlantId' => session('active_plant_id'),
            'designations' => Designation::forWorkspace(auth()->user(), session('active_plant_id'))
                ->with('plant:id,name')->orderBy('name')->orderBy('plant_id')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
        ]);


        $plantId = session('active_plant_id');
        abort_unless($plantId && app(\App\Services\PlantContextService::class)
            ->accessiblePlants($request->user())->whereKey($plantId)->exists(), 403, 'Select an accessible plant first.');
        Designation::create([...$validated, 'plant_id' => $plantId]);

        return redirect()->back()->with('success', 'Designation created successfully.');
    }

    public function update(Request $request, Designation $designation)
    {
        $this->authorizeModule('edit');
        $this->checkSelectedPlant($designation);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
        ]);

        $designation->update($validated);

        return redirect()->back()->with('success', 'Designation updated successfully.');
    }

    public function destroy(Designation $designation)
    {
        $this->authorizeModule('delete');
        $this->checkSelectedPlant($designation);

        $designation->delete();

        return redirect()->back()->with('success', 'Designation deleted successfully.');
    }
    private function checkSelectedPlant(Designation $designation): void
    {
        $plantId = session('active_plant_id');
        abort_unless($plantId && Designation::forWorkspace(auth()->user(), $plantId)
            ->whereKey($designation->id)->exists(), 404);
    }
}
