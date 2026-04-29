<?php

namespace App\Http\Controllers;

use App\Models\MachineType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class MachineTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'machine_types';

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('MachineTypes/Index', [
            'machineTypes' => MachineType::where('plant_id', $plantId)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $validated['plant_id'] = $plantId;
        MachineType::create($validated);

        return redirect()->back()->with('success', 'Machine Type created successfully.');
    }

    public function update(Request $request, MachineType $machinetype)
    {
        $this->authorizeModule('edit');
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $machinetype->update($validated);

        return redirect()->back()->with('success', 'Machine Type updated successfully.');
    }

    public function destroy(MachineType $machinetype)
    {
        $this->authorizeModule('delete');
        $machinetype->delete();
        
        return redirect()->back()->with('success', 'Machine Type deleted.');
    }
}
