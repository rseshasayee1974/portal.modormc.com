<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Personnel;
use App\Models\Department;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'attendance';

    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        return Inertia::render('Attendances/Index', [
            'attendances' => Attendance::with(['personnel.department', 'personnel.designation', 'shift'])
                ->where('plant_id', $activePlantId)
                ->orderBy('attendance_date', 'desc')
                ->get(),
            'personnel' => Personnel::with(['department', 'designation'])
                ->where('plant_id', $activePlantId)
                ->get(),
            'departments' => Department::query()->get(['id', 'name', 'code']),
            'shifts' => Shift::get(['id', 'shift_name', 'start_time', 'end_time']),
            'statuses' => ['present', 'absent'],
            // 'statuses' => ['present', 'absent', 'half_day', 'leave', 'holiday', 'weekoff', 'on_duty'],
            'sources' => ['manual', 'biometric', 'mobile', 'web']
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $activePlantId = session('active_plant_id');

        // Handle bulk attendance submission (by department or multi-employee selection)
        if ($request->has('attendances') && is_array($request->attendances)) {
            $request->validate([
                'attendance_date' => 'required|date',
                'attendances' => 'required|array|min:1',
                'attendances.*.personnel_id' => 'required|exists:mm_personnels,id',
                'attendances.*.status' => 'required|in:present,absent,half_day,leave,holiday,weekoff,on_duty',
                'attendances.*.check_in' => 'nullable|date',
                'attendances.*.check_out' => 'nullable|date|after_or_equal:attendances.*.check_in',
                'attendances.*.notes' => 'nullable|string|max:1000',
            ]);

            $attendanceDate = date('Y-m-d', strtotime($request->attendance_date));

            DB::transaction(function () use ($request, $attendanceDate, $activePlantId) {
                foreach ($request->attendances as $item) {
                    $personnelId = $item['personnel_id'];
                    $status = $item['status'] ?? 'present';
                    $checkInRaw = !empty($item['check_in']) && $status !== 'absent' ? date('Y-m-d H:i:s', strtotime($item['check_in'])) : null;
                    $checkOutRaw = !empty($item['check_out']) && $status !== 'absent' ? date('Y-m-d H:i:s', strtotime($item['check_out'])) : null;

                    $metrics = $this->calculateAttendanceMetrics($personnelId, $attendanceDate, $checkInRaw, $checkOutRaw, $status);

                    $record = Attendance::withTrashed()->updateOrCreate(
                        [
                            'personnel_id' => $personnelId,
                            'attendance_date' => $attendanceDate,
                        ],
                        [
                            'plant_id' => $activePlantId,
                            'shift_id' => $item['shift_id'] ?? null,
                            'check_in' => $checkInRaw,
                            'check_out' => $checkOutRaw,
                            'worked_hours' => $metrics['worked_hours'],
                            'overtime_hours' => $metrics['overtime_hours'],
                            'late_hours' => $metrics['late_hours'],
                            'status' => $status,
                            'is_late' => $metrics['is_late'],
                            'is_early_departure' => $metrics['is_early_departure'],
                            'source' => $item['source'] ?? 'manual',
                            'notes' => $item['notes'] ?? null,
                        ]
                    );

                    if ($record->trashed()) {
                        $record->restore();
                    }
                }
            });

            return redirect()->back()->with('success', 'Attendance records logged successfully.');
        }

        // Single attendance record store
        $validated = $request->validate([
            'personnel_id' => 'required|exists:mm_personnels,id',
            'shift_id' => 'nullable|exists:mm_shifts,id',
            'attendance_date' => 'required|date',
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date|after_or_equal:check_in',
            'worked_hours' => 'nullable|numeric|min:0|max:24',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'late_hours' => 'nullable|numeric|min:0|max:24',
            'status' => 'required|in:present,absent,half_day,leave,holiday,weekoff,on_duty',
            'is_late' => 'boolean',
            'is_early_departure' => 'boolean',
            'source' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['plant_id'] = $activePlantId;
        $validated['attendance_date'] = date('Y-m-d', strtotime($validated['attendance_date']));

        $checkInRaw = !empty($validated['check_in']) && $validated['status'] !== 'absent' ? date('Y-m-d H:i:s', strtotime($validated['check_in'])) : null;
        $checkOutRaw = !empty($validated['check_out']) && $validated['status'] !== 'absent' ? date('Y-m-d H:i:s', strtotime($validated['check_out'])) : null;

        $metrics = $this->calculateAttendanceMetrics($validated['personnel_id'], $validated['attendance_date'], $checkInRaw, $checkOutRaw, $validated['status']);

        $validated['check_in'] = $checkInRaw;
        $validated['check_out'] = $checkOutRaw;
        $validated['worked_hours'] = $metrics['worked_hours'];
        $validated['overtime_hours'] = $metrics['overtime_hours'];
        $validated['late_hours'] = $metrics['late_hours'];
        $validated['is_late'] = $metrics['is_late'];
        $validated['is_early_departure'] = $metrics['is_early_departure'];

        $record = Attendance::withTrashed()->updateOrCreate(
            [
                'personnel_id' => $validated['personnel_id'],
                'attendance_date' => $validated['attendance_date'],
            ],
            $validated
        );

        if ($record->trashed()) {
            $record->restore();
        }

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
                        ->where('id', '!=', $attendance->id)
                        ->whereNull('deleted_at');
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
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['attendance_date'] = date('Y-m-d', strtotime($validated['attendance_date']));

        $checkInRaw = !empty($validated['check_in']) && $validated['status'] !== 'absent' ? date('Y-m-d H:i:s', strtotime($validated['check_in'])) : null;
        $checkOutRaw = !empty($validated['check_out']) && $validated['status'] !== 'absent' ? date('Y-m-d H:i:s', strtotime($validated['check_out'])) : null;

        $metrics = $this->calculateAttendanceMetrics($validated['personnel_id'], $validated['attendance_date'], $checkInRaw, $checkOutRaw, $validated['status']);

        $validated['check_in'] = $checkInRaw;
        $validated['check_out'] = $checkOutRaw;
        $validated['worked_hours'] = $metrics['worked_hours'];
        $validated['overtime_hours'] = $metrics['overtime_hours'];
        $validated['late_hours'] = $metrics['late_hours'];
        $validated['is_late'] = $metrics['is_late'];
        $validated['is_early_departure'] = $metrics['is_early_departure'];

        $attendance->update($validated);

        return redirect()->back()->with('success', 'Attendance record updated successfully.');
    }

    public function destroy(Attendance $attendance)
    {
        $this->authorizeModule('delete');

        $attendance->delete();

        return redirect()->back()->with('success', 'Attendance record deleted successfully.');
    }

    /**
     * Calculate worked_hours, overtime_hours, late_hours, is_late, and is_early_departure
     * based on shift start/end times defined on the Personnel model.
     */
    private function calculateAttendanceMetrics($personnelId, string $attendanceDate, ?string $checkIn, ?string $checkOut, string $status): array
    {
        $metrics = [
            'worked_hours' => 0.0,
            'overtime_hours' => 0.0,
            'late_hours' => 0.0,
            'is_late' => false,
            'is_early_departure' => false,
        ];

        if ($status === 'absent' || !$checkIn) {
            return $metrics;
        }

        $inTimestamp = strtotime($checkIn);
        $outTimestamp = $checkOut ? strtotime($checkOut) : null;

        if ($outTimestamp && $outTimestamp > $inTimestamp) {
            $worked = ($outTimestamp - $inTimestamp) / 3600;
            $metrics['worked_hours'] = round(min(24, max(0, $worked)), 1);
        }

        $personnel = Personnel::find($personnelId);
        if (!$personnel) {
            return $metrics;
        }

        // Shift start time check (Late calculation)
        if (!empty($personnel->shift_start_time)) {
            $shiftStartTimeStr = date('Y-m-d', $inTimestamp) . ' ' . $personnel->shift_start_time;
            $shiftStartTimestamp = strtotime($shiftStartTimeStr);

            if ($inTimestamp > $shiftStartTimestamp) {
                $late = ($inTimestamp - $shiftStartTimestamp) / 3600;
                $metrics['late_hours'] = round(min(24, max(0, $late)), 1);
                $metrics['is_late'] = true;
            }
        }

        // Overtime & Early Departure check based on shift start & end times
        if (!empty($personnel->shift_start_time) && !empty($personnel->shift_end_time)) {
            $sStart = strtotime('1970-01-01 ' . $personnel->shift_start_time);
            $sEnd = strtotime('1970-01-01 ' . $personnel->shift_end_time);
            if ($sEnd <= $sStart) {
                $sEnd += 86400; // overnight shift
            }
            $scheduledHrs = ($sEnd - $sStart) / 3600;

            if ($metrics['worked_hours'] > $scheduledHrs) {
                $ot = $metrics['worked_hours'] - $scheduledHrs;
                $metrics['overtime_hours'] = round(min(24, max(0, $ot)), 1);
            }

            if ($outTimestamp) {
                $shiftEndTimeStr = date('Y-m-d', $outTimestamp) . ' ' . $personnel->shift_end_time;
                $shiftEndTimestamp = strtotime($shiftEndTimeStr);
                if ($outTimestamp < $shiftEndTimestamp) {
                    $metrics['is_early_departure'] = true;
                }
            }
        }

        return $metrics;
    }
}

