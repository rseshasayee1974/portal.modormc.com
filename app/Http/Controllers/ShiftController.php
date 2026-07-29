<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Personnel;
use App\Models\EmployeeShift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ShiftController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'shifts';

    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        return Inertia::render('Shifts/Index', [
            'shifts' => Shift::latest()->get(),
            'personnel' => Personnel::where('plant_id', $activePlantId)->get(['id', 'first_name', 'last_name', 'employee_code']),
            'employeeShifts' => EmployeeShift::with(['personnel', 'shift'])
                ->whereHas('personnel', function ($q) use ($activePlantId) {
                    $q->where('plant_id', $activePlantId);
                })
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'shift_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'grace_time' => 'nullable|date_format:H:i:s',
            'working_hours' => 'required|numeric|min:0',
            'is_night_shift' => 'boolean',
        ]);

        Shift::create($validated);

        return redirect()->back()->with('success', 'Shift created successfully.');
    }

    public function update(Request $request, Shift $shift)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'shift_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'grace_time' => 'nullable|date_format:H:i:s',
            'working_hours' => 'required|numeric|min:0',
            'is_night_shift' => 'boolean',
        ]);

        $shift->update($validated);

        return redirect()->back()->with('success', 'Shift updated successfully.');
    }

    public function destroy(Shift $shift)
    {
        $this->authorizeModule('delete');

        $shift->delete();

        return redirect()->back()->with('success', 'Shift deleted successfully.');
    }

    public function assignShift(Request $request)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'shift_id' => 'required|exists:mm_shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        // Format dates
        $validated['effective_from'] = date('Y-m-d', strtotime($validated['effective_from']));
        if (!empty($validated['effective_to'])) {
            $validated['effective_to'] = date('Y-m-d', strtotime($validated['effective_to']));
        }

        EmployeeShift::create($validated);

        return redirect()->back()->with('success', 'Shift assigned to employee successfully.');
    }

    public function removeShiftAssignment(EmployeeShift $employeeShift)
    {
        $this->authorizeModule('delete');

        $employeeShift->delete();

        return redirect()->back()->with('success', 'Shift assignment removed successfully.');
    }
}
