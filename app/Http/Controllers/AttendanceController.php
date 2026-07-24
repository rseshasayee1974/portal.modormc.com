<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Personnel;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'attendances';

    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        return Inertia::render('Attendances/Index', [
            'attendances' => Attendance::with(['personnel', 'shift'])
                ->where('plant_id', $activePlantId)
                ->latest()
                ->get(),
            'personnel' => Personnel::where('plant_id', $activePlantId)->get(['id', 'first_name', 'last_name', 'employee_code']),
            'shifts' => Shift::get(['id', 'shift_name', 'start_time', 'end_time']),
            'statuses' => ['present', 'absent', 'half_day', 'leave', 'holiday', 'weekoff', 'on_duty'],
            'sources' => ['manual', 'biometric', 'mobile', 'web']
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'shift_id' => 'nullable|exists:mm_shifts,id',
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('mm_attendances')->where(function ($query) use ($request) {
                    return $query->where('personnel_id', $request->personnel_id)
                        ->where('attendance_date', date('Y-m-d', strtotime($request->attendance_date)));
                })
            ],
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date|after_or_equal:check_in',
            'worked_hours' => 'nullable|numeric|min:0|max:24',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'late_hours' => 'nullable|numeric|min:0|max:24',
            'status' => 'required|in:present,absent,half_day,leave,holiday,weekoff,on_duty',
            'is_late' => 'boolean',
            'is_early_departure' => 'boolean',
            'source' => 'required|string',
        ]);

        $validated['plant_id'] = session('active_plant_id');
        $validated['attendance_date'] = date('Y-m-d', strtotime($validated['attendance_date']));

        if (!empty($validated['check_in'])) {
            $validated['check_in'] = date('Y-m-d H:i:s', strtotime($validated['check_in']));
        }
        if (!empty($validated['check_out'])) {
            $validated['check_out'] = date('Y-m-d H:i:s', strtotime($validated['check_out']));
        }

        Attendance::create($validated);

        return redirect()->back()->with('success', 'Attendance record logged successfully.');
    }

    public function update(Request $request, Attendance $attendance)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'shift_id' => 'nullable|exists:mm_shifts,id',
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('mm_attendances')->where(function ($query) use ($request, $attendance) {
                    return $query->where('personnel_id', $request->personnel_id)
                        ->where('attendance_date', date('Y-m-d', strtotime($request->attendance_date)))
                        ->where('id', '!=', $attendance->id);
                })
            ],
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date|after_or_equal:check_in',
            'worked_hours' => 'nullable|numeric|min:0|max:24',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'late_hours' => 'nullable|numeric|min:0|max:24',
            'status' => 'required|in:present,absent,half_day,leave,holiday,weekoff,on_duty',
            'is_late' => 'boolean',
            'is_early_departure' => 'boolean',
            'source' => 'required|string',
        ]);

        $validated['attendance_date'] = date('Y-m-d', strtotime($validated['attendance_date']));

        if (!empty($validated['check_in'])) {
            $validated['check_in'] = date('Y-m-d H:i:s', strtotime($validated['check_in']));
        } else {
            $validated['check_in'] = null;
        }
        if (!empty($validated['check_out'])) {
            $validated['check_out'] = date('Y-m-d H:i:s', strtotime($validated['check_out']));
        } else {
            $validated['check_out'] = null;
        }

        $attendance->update($validated);

        return redirect()->back()->with('success', 'Attendance record updated successfully.');
    }

    public function destroy(Attendance $attendance)
    {
        $this->authorizeModule('delete');

        $attendance->delete();

        return redirect()->back()->with('success', 'Attendance record deleted successfully.');
    }
}
