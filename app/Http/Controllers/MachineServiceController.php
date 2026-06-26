<?php

namespace App\Http\Controllers;

use App\Models\MachineService;
use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class MachineServiceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'machine_services';

    public function index()
    {
        $this->authorizeModule('menu');

        $services = MachineService::with(['machine'])
            ->where('plant_id', session('active_plant_id'))
            ->latest()
            ->get();

        return Inertia::render('MachineServices/Index', [
            'services' => $services,
            'machines' => MachinesDropdown()->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'truck_id' => 'required|exists:mm_machines,id',
            'service_type' => 'required|integer',
            'last_service_km' => 'required|numeric',
            'next_service_km' => 'required|numeric',
            'current_running_km' => 'required|numeric',
            'service_hr_km' => 'nullable|string|max:50',
            'service_date' => 'nullable|date',
            'notes' => 'nullable|string|max:250',
            'status' => 'required|integer',
        ]);

        $validated['plant_id'] = session('active_plant_id');

        MachineService::create($validated);

        return redirect()->back()->with('success', 'Machine service entry created successfully.');
    }

    public function update(Request $request, MachineService $machineService)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'truck_id' => 'required|exists:mm_machines,id',
            'service_type' => 'required|integer',
            'last_service_km' => 'required|numeric',
            'next_service_km' => 'required|numeric',
            'current_running_km' => 'required|numeric',
            'service_hr_km' => 'nullable|string|max:50',
            'service_date' => 'nullable|date',
            'notes' => 'nullable|string|max:250',
            'status' => 'required|integer',
        ]);

        $machineService->update($validated);

        return redirect()->back()->with('success', 'Machine service entry updated successfully.');
    }

    public function destroy(MachineService $machineService)
    {
        $this->authorizeModule('delete');
        $machineService->delete();

        return redirect()->back()->with('success', 'Machine service entry deleted successfully.');
    }
}
