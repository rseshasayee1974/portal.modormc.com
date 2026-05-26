<?php

namespace App\Http\Controllers;

use App\Models\MachineTracker;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class MachineTrackerController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'machines';

    public function index()
    {
        $this->authorizeModule('menu');

        $trackers = MachineTracker::with(['machine', 'operator', 'company'])
            ->where('plant_id', session('active_plant_id'))
            ->latest()
            ->get();

        return Inertia::render('MachineTrackers/Index', [
            'trackers' => $trackers,
            'machines' => MachinesDropdown()->toArray(),
            'operators' => User::select('id', 'username')->orderBy('username')->get()->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'machine_id' => 'required|exists:mm_machines,id',
            'operation_type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:10',
            'operator_id' => 'nullable|exists:users,id',
            'opening' => 'nullable|date',
            'closing' => 'nullable|date',
            'odometer_start' => 'nullable|numeric',
            'odometer_end' => 'nullable|numeric',
            'hourmeter_start' => 'nullable|numeric',
            'hourmeter_end' => 'nullable|numeric',
            'eb_start' => 'required|numeric',
            'eb_close' => 'required|numeric',
            'opening_hsd' => 'nullable|numeric',
            'closing_hsd' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'fuel' => 'nullable|numeric',
            'fuel_filled_on' => 'nullable|date',
            'last_fuel_filled_km' => 'required|numeric',
            'fuel_filled_km' => 'nullable|numeric',
            'pump_name' => 'nullable|string|max:250',
            'pump_reading' => 'nullable|string|max:250',
            'amount' => 'nullable|numeric',
            'shift' => 'required|integer',
        ]);

        $validated['plant_id'] = session('active_plant_id');

        MachineTracker::create($validated);

        return redirect()->back()->with('success', 'Tracker log sheet created successfully.');
    }

    public function update(Request $request, MachineTracker $machineTracker)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'machine_id' => 'required|exists:mm_machines,id',
            'operation_type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:10',
            'operator_id' => 'nullable|exists:users,id',
            'opening' => 'nullable|date',
            'closing' => 'nullable|date',
            'odometer_start' => 'nullable|numeric',
            'odometer_end' => 'nullable|numeric',
            'hourmeter_start' => 'nullable|numeric',
            'hourmeter_end' => 'nullable|numeric',
            'eb_start' => 'required|numeric',
            'eb_close' => 'required|numeric',
            'opening_hsd' => 'nullable|numeric',
            'closing_hsd' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'fuel' => 'nullable|numeric',
            'fuel_filled_on' => 'nullable|date',
            'last_fuel_filled_km' => 'required|numeric',
            'fuel_filled_km' => 'nullable|numeric',
            'pump_name' => 'nullable|string|max:250',
            'pump_reading' => 'nullable|string|max:250',
            'amount' => 'nullable|numeric',
            'shift' => 'required|integer',
        ]);

        $machineTracker->update($validated);

        return redirect()->back()->with('success', 'Tracker log sheet updated successfully.');
    }

    public function destroy(MachineTracker $machineTracker)
    {
        $this->authorizeModule('delete');
        $machineTracker->delete();

        return redirect()->back()->with('success', 'Tracker log sheet deleted successfully.');
    }
}
