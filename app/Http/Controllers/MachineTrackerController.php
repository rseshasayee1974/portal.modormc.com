<?php

namespace App\Http\Controllers;

use App\Models\MachineTracker;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class MachineTrackerController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'machine_tracker';

    public function index()
    {
        $this->authorizeModule('menu');

        $trackers = MachineTracker::with(['machine', 'operator', 'company'])
            ->where('plant_id', session('active_plant_id'))
            ->orderByRaw('COALESCE(opening, created) DESC')->orderByDesc('id')
            ->get();

            // $OperatorsDropdown = OperatorsDropdown();
            // dd($OperatorsDropdown);
        return Inertia::render('MachineTrackers/Index', [
            'trackers' => $trackers,
            'machines' => MachinesDropdown()->toArray(),
            'operators' => DriversOperatorsDropdown(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $this->validatedData($request);
        $validated['plant_id'] = session('active_plant_id');
        MachineTracker::create($validated);
        return redirect()->back()->with('success', 'Daily machine tracker saved successfully.');
    }

    public function update(Request $request, MachineTracker $machineTracker)
    {
        $this->authorizeModule('edit');
        abort_unless((int) $machineTracker->plant_id === (int) session('active_plant_id'), 403);
        $machineTracker->update($this->validatedData($request));
        return redirect()->back()->with('success', 'Daily machine tracker updated successfully.');
    }

    private function validatedData(Request $request): array
    {
        $plantId = app(\App\Services\PlantContextService::class)->requirePlantId();
        $validated = $request->validate([
            'machine_id' => ['required', Rule::exists('mm_machines', 'id')->where('plant_id', $plantId)->whereNull('deleted_at')],
            'operator_id' => ['nullable', Rule::exists('mm_personnels', 'id')->where('plant_id', $plantId)->whereNull('deleted_at')],
            'operation_type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:10',
            'opening' => 'required|date',
            'closing' => 'nullable|date|after_or_equal:opening',
            'odometer_start' => 'nullable|required_with:odometer_end|numeric|decimal:0,2|min:0',
            'odometer_end' => 'nullable|required_with:odometer_start|numeric|decimal:0,2|min:0|gte:odometer_start',
            'hourmeter_start' => 'nullable|numeric|min:0',
            'hourmeter_end' => 'nullable|numeric|min:0|gte:hourmeter_start',
            'eb_start' => 'nullable|numeric|min:0',
            'eb_close' => 'nullable|numeric|min:0|gte:eb_start',
            'opening_hsd' => 'nullable|numeric|min:0',
            'closing_hsd' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'fuel' => 'nullable|numeric|min:0',
            'fuel_filled_on' => 'nullable|date',
            'last_fuel_filled_km' => 'nullable|numeric|min:0',
            'fuel_filled_km' => 'nullable|numeric|min:0',
            'pump_name' => 'nullable|string|max:250',
            'pump_reading' => 'nullable|string|max:250',
            'amount' => 'nullable|numeric|min:0',
            'shift' => ['required', 'integer', Rule::in([-1, 1, 2, 3])],
        ]);
        foreach (['odometer_start', 'odometer_end', 'hourmeter_start', 'hourmeter_end', 'eb_start', 'eb_close',
            'opening_hsd', 'closing_hsd', 'fuel', 'last_fuel_filled_km', 'fuel_filled_km', 'amount'] as $field) {
            $validated[$field] = $validated[$field] ?? 0;
        }
        return $validated;
    }

    public function destroy(MachineTracker $machineTracker)
    {
        $this->authorizeModule('delete');
        abort_unless((int) $machineTracker->plant_id === (int) session('active_plant_id'), 403);
        $machineTracker->delete();
        return redirect()->back()->with('success', 'Tracker log sheet deleted successfully.');
    }
}
