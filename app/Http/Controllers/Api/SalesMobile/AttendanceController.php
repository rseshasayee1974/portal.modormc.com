<?php

/*
Author: Cipher
Created: 2026-10-21 15:47:59 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Models\Attendance;
use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileAccess;
use App\Services\MobileAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private MobileAttendanceService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'from_date' => 'sometimes|required|date_format:Y-m-d',
            'to_date' => 'sometimes|required|date_format:Y-m-d'.($request->filled('from_date') ? '|after_or_equal:from_date' : ''),
            'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1',
        ]);
        $personnel = $this->service->personnel($request->user(), MobileAccess::plantId());
        $entries = $this->service->query($personnel)
            ->when(isset($data['from_date']), fn ($q) => $q->whereDate('attendance_date', '>=', $data['from_date']))
            ->when(isset($data['to_date']), fn ($q) => $q->whereDate('attendance_date', '<=', $data['to_date']))
            ->orderByDesc('attendance_date')->orderByDesc('id')->paginate($data['per_page'] ?? 20)
            ->through(fn ($entry) => $this->service->serialize($entry));

        return response()->json(['success' => true, 'data' => $entries]);
    }

    public function current(Request $request)
    {
        $plantId = MobileAccess::plantId();
        $personnel = $this->service->personnel($request->user(), $plantId);
        $now = CarbonImmutable::now(Attendance::TIMEZONE);
        $schedule = $this->service->currentSchedule($personnel, $now);
        $entry = $this->service->query($personnel)->whereNotNull('check_in')->whereNull('check_out')->orderBy('id')->first()
            ?? $this->service->query($personnel)->whereDate('attendance_date', $schedule['attendance_date'])->first();

        return response()->json(['success' => true, 'data' => [
            'server_time' => $now->format('Y-m-d H:i:s'), 'timezone' => $now->timezoneName,
            'work_date' => $schedule['attendance_date'], 'attendance' => $entry ? $this->service->serialize($entry) : null,
        ]]);
    }

    public function show(Request $request, int $attendance)
    {
        $personnel = $this->service->personnel($request->user(), MobileAccess::plantId());

        return response()->json(['success' => true, 'data' => $this->service->serialize($this->service->query($personnel)->findOrFail($attendance))]);
    }

    public function checkIn(Request $request)
    {
        $request->validate(['notes' => 'sometimes|nullable|string|max:1000']);
        $this->validatePunchFields($request, ['notes']);
        [$entry, $created] = $this->service->checkIn($request->user(), MobileAccess::plantId(), $request->input('notes'));

        return response()->json(['success' => true, 'data' => $this->service->serialize($entry)], $created ? 201 : 200);
    }

    public function checkOut(Request $request, int $attendance)
    {
        $this->validatePunchFields($request, []);
        $entry = $this->service->checkOut($request->user(), MobileAccess::plantId(), $attendance);

        return response()->json(['success' => true, 'data' => $this->service->serialize($entry)]);
    }

    private function validatePunchFields(Request $request, array $allowed): void
    {
        foreach (array_diff(array_keys($request->all()), $allowed) as $field) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'This field is managed by the server.']);
        }
    }
}
