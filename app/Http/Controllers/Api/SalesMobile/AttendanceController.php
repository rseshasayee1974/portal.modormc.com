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
        MobileAccess::authorize('ATTENDANCE', 'VIEW');
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

        return response()->json(['success' => true, 'user' => $request->user()->only(['id', 'username']), 'data' => $entries]);
    }

    public function current(Request $request)
    {
        MobileAccess::authorize('ATTENDANCE', 'VIEW');
        $plantId = MobileAccess::plantId();
        $personnel = $this->service->personnel($request->user(), $plantId);
        $now = CarbonImmutable::now(Attendance::TIMEZONE);
        $schedule = $this->service->currentSchedule($personnel, $now);
        $entry = $this->service->query($personnel)->whereNotNull('check_in')->whereNull('check_out')->orderBy('id')->first()
            ?? $this->service->query($personnel)->whereDate('attendance_date', $schedule['attendance_date'])->first();

        return response()->json(['success' => true, 'data' => [
            'server_time' => $now->format('Y-m-d H:i:s'), 'timezone' => $now->timezoneName,
            'user' => $request->user()->only(['id', 'username']),
            'shift' => $schedule['shift'],
            'shift_id' => $schedule['shift_id'], 'scheduled_start' => $schedule['scheduled_start'],
            'scheduled_end' => $schedule['scheduled_end'], 'accepts_check_in_time' => $schedule['scheduled_start'] === null,
            'work_date' => $schedule['attendance_date'], 'attendance' => $entry ? $this->service->serialize($entry) : null,
        ]]);
    }

    public function show(Request $request, int $attendance)
    {
        MobileAccess::authorize('ATTENDANCE', 'VIEW');
        $personnel = $this->service->personnel($request->user(), MobileAccess::plantId());

        return response()->json(['success' => true, 'data' => $this->service->serialize($this->service->query($personnel)->findOrFail($attendance))]);
    }

    public function downloadPdf(Request $request, int $attendance)
    {
        MobileAccess::authorize('ATTENDANCE', 'PDF');
        $personnel = $this->service->personnel($request->user(), MobileAccess::plantId());
        $entry = $this->service->query($personnel)->with(['personnel', 'shift', 'plant'])->findOrFail($attendance);
        $escape = fn ($value) => e((string) ($value ?? '—'));
        $name = trim(($entry->personnel?->first_name ?? '').' '.($entry->personnel?->last_name ?? ''));
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#222;font-size:12px}h1{font-size:20px;margin-bottom:4px}.muted{color:#666}table{width:100%;border-collapse:collapse;margin-top:20px}td{padding:10px;border:1px solid #ddd}td:first-child{font-weight:bold;width:35%;background:#f6f6f6}</style></head><body>'
            .'<h1>Attendance Record</h1><div class="muted">'. $escape($entry->plant?->name) .'</div><table>'
            .'<tr><td>Employee</td><td>'.$escape($name).'</td></tr>'
            .'<tr><td>Employee code</td><td>'.$escape($entry->personnel?->employee_code).'</td></tr>'
            .'<tr><td>Date</td><td>'.$escape($entry->attendance_date?->format('Y-m-d')).'</td></tr>'
            .'<tr><td>Shift</td><td>'.$escape($entry->shift?->name).'</td></tr>'
            .'<tr><td>Check in</td><td>'.$escape($entry->check_in?->format('Y-m-d H:i:s')).'</td></tr>'
            .'<tr><td>Check out</td><td>'.$escape($entry->check_out?->format('Y-m-d H:i:s')).'</td></tr>'
            .'<tr><td>Worked hours</td><td>'.$escape($entry->worked_hours).'</td></tr>'
            .'<tr><td>Status</td><td>'.$escape($entry->status).'</td></tr>'
            .'<tr><td>Notes</td><td>'.$escape($entry->notes).'</td></tr></table></body></html>';

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download('attendance-'.$entry->attendance_date->format('Y-m-d').'.pdf');
    }

    public function checkIn(Request $request)
    {
        MobileAccess::authorize('ATTENDANCE', 'CREATE');
        $request->validate([
            'notes' => 'sometimes|nullable|string|max:1000',
            'check_in' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
        ]);
        $this->validatePunchFields($request, ['notes', 'check_in']);
        [$entry, $created] = $this->service->checkIn($request->user(), MobileAccess::plantId(), $request->input('notes'), $request->input('check_in'));

        return response()->json(['success' => true, 'data' => $this->service->serialize($entry)], $created ? 201 : 200);
    }

    public function checkOut(Request $request, int $attendance)
    {
        MobileAccess::authorize('ATTENDANCE', 'UPDATE');
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
