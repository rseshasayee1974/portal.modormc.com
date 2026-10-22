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
                'attendances.*.personnel_id' => ['required', $this->personnelRule()],
                'attendances.*.shift_id' => 'nullable|exists:mm_shifts,id',
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
                    $checkInRaw = $this->normalizePunchTime($item['check_in'] ?? null, $status);
                    $checkOutRaw = $this->normalizePunchTime($item['check_out'] ?? null, $status);

                    $metrics = $this->calculateAttendanceMetrics($personnelId, $attendanceDate, $checkInRaw, $checkOutRaw, $status, $item['shift_id'] ?? null);

                    $record = Attendance::withTrashed()->updateOrCreate(
                        [
                            'personnel_id' => $personnelId,
                            'attendance_date' => $attendanceDate,
                        ],
                        [
                            ...$metrics,
                            'plant_id' => $activePlantId,
                            'shift_id' => $metrics['shift_id'],
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
            'personnel_id' => ['required', $this->personnelRule()],
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

        $checkInRaw = $this->normalizePunchTime($validated['check_in'] ?? null, $validated['status']);
        $checkOutRaw = $this->normalizePunchTime($validated['check_out'] ?? null, $validated['status']);

        $metrics = $this->calculateAttendanceMetrics($validated['personnel_id'], $validated['attendance_date'], $checkInRaw, $checkOutRaw, $validated['status'], $validated['shift_id'] ?? null);

        $validated = array_merge($validated, $metrics);
        $validated['check_in'] = $checkInRaw;
        $validated['check_out'] = $checkOutRaw;

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
            'personnel_id' => ['required', $this->personnelRule()],
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

        $checkInRaw = $this->normalizePunchTime($validated['check_in'] ?? null, $validated['status']);
        $checkOutRaw = $this->normalizePunchTime($validated['check_out'] ?? null, $validated['status']);

        $metrics = $this->calculateAttendanceMetrics($validated['personnel_id'], $validated['attendance_date'], $checkInRaw, $checkOutRaw, $validated['status'], $validated['shift_id'] ?? null);

        $validated = array_merge($validated, $metrics);
        $validated['check_in'] = $checkInRaw;
        $validated['check_out'] = $checkOutRaw;

        $attendance->update($validated);

        return redirect()->back()->with('success', 'Attendance record updated successfully.');
    }

    public function destroy(Attendance $attendance)
    {
        $this->authorizeModule('delete');

        $attendance->delete();

        return redirect()->back()->with('success', 'Attendance record deleted successfully.');
    }

    private function normalizePunchTime(?string $value, string $status): ?string
    {
        if (! filled($value) || $status === 'absent') {
            return null;
        }

        return \Carbon\CarbonImmutable::parse($value, Attendance::TIMEZONE)
            ->setTimezone(Attendance::TIMEZONE)->format('Y-m-d H:i:s');
    }

    private function personnelRule()
    {
        return Rule::exists('mm_personnels', 'id')->where('plant_id', session('active_plant_id'))->whereNull('deleted_at');
    }

    private function calculateAttendanceMetrics($personnelId, string $attendanceDate, ?string $checkIn, ?string $checkOut, string $status, ?int $shiftId = null): array
    {
        $personnel = Personnel::where('plant_id', session('active_plant_id'))->findOrFail($personnelId);
        $service = app(\App\Services\AttendanceMetricsService::class);
        if (! $checkIn || ! in_array($status, ['present', 'on_duty', 'half_day'], true)) {
            return ['shift_id' => $shiftId] + $service->calculate($checkIn, $checkOut, [
                'attendance_timezone' => Attendance::TIMEZONE,
                'scheduled_start' => null, 'scheduled_end' => null, 'scheduled_hours' => null,
            ], $status);
        }
        $schedule = $service->schedule($personnel, $attendanceDate,
            Attendance::TIMEZONE, $shiftId);

        return ['shift_id' => $schedule['shift_id']] + $service->calculate($checkIn, $checkOut, $schedule, $status);
    }
}
