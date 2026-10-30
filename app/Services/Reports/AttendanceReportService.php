<?php

/*
Author: Cipher
Created: 2026-10-27 15:37:34 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Reports;

use App\Models\Attendance;
use App\Services\PlantContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceReportService implements ReportServiceInterface
{
    public const STATUSES = ['present', 'absent', 'half_day', 'leave', 'holiday', 'weekoff', 'on_duty'];

    public const COLUMNS = [
        'date' => 'Date', 'employee_code' => 'Employee Code', 'employee_name' => 'Employee',
        'department' => 'Department', 'shift' => 'Shift', 'status_label' => 'Status',
        'check_in' => 'Check In', 'check_out' => 'Check Out', 'worked_hours' => 'Worked Hours',
        'overtime_hours' => 'Overtime Hours', 'late_hours' => 'Late Hours', 'source' => 'Source', 'notes' => 'Notes',
    ];

    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $filters = Validator::make($params, [
            'start' => 'nullable|date', 'end' => 'nullable|date|after_or_equal:start',
            'employee_id' => 'nullable|integer|min:1', 'attendance_status' => ['nullable', Rule::in(self::STATUSES)],
        ])->validate();
        $plant = $this->ctx->requirePlantId();
        $query = Attendance::withoutGlobalScope('plant_id')->where('plant_id', $plant)
            ->whereHas('personnel', fn ($q) => $q->withoutGlobalScope('plant_id')
                ->where(fn ($scope) => $scope->where('plant_id', $plant)->orWhereNull('plant_id')))
            ->with(['personnel' => fn ($q) => $q->withoutGlobalScope('plant_id')->with('department'), 'shift']);
        if (!empty($filters['start'])) $query->whereDate('attendance_date', '>=', Carbon::parse($filters['start'])->toDateString());
        if (!empty($filters['end'])) $query->whereDate('attendance_date', '<=', Carbon::parse($filters['end'])->toDateString());
        if (!empty($filters['employee_id'])) $query->where('personnel_id', $filters['employee_id']);
        if (!empty($filters['attendance_status'])) $query->where('status', $filters['attendance_status']);
        $records = $query->orderBy('attendance_date')->orderBy('personnel_id')->orderBy('id')->get();
        $rows = $records->map(fn ($record) => [
            'id' => $record->id, 'date' => $record->attendance_date->format('Y-m-d'),
            'employee_code' => $record->personnel->employee_code ?: 'EMP-'.$record->personnel_id,
            'employee_name' => trim($record->personnel->first_name.' '.$record->personnel->last_name),
            'department' => $record->personnel->department?->name ?? '—', 'shift' => $record->shift?->shift_name ?? '—',
            'status' => $record->status, 'status_label' => ucwords(str_replace('_', ' ', $record->status)),
            'check_in' => $record->check_in?->format('h:i A') ?? '—',
            'check_out' => $record->check_out?->format('h:i A') ?? '—',
            'worked_hours' => (float) ($record->worked_hours ?? 0), 'overtime_hours' => (float) ($record->overtime_hours ?? 0),
            'late_hours' => (float) ($record->late_hours ?? 0), 'source' => $record->source ?? '—', 'notes' => $record->notes ?? '',
        ])->values();
        return [
            'transactions' => $rows, 'columns' => self::COLUMNS, 'total_records' => $rows->count(),
            'total_employees' => $records->pluck('personnel_id')->unique()->count(),
            'status_counts' => array_replace(array_fill_keys(self::STATUSES, 0), $records->countBy('status')->all()),
            'totals' => [
                'worked_hours' => round($rows->sum('worked_hours'), 2),
                'overtime_hours' => round($rows->sum('overtime_hours'), 2), 'late_hours' => round($rows->sum('late_hours'), 2),
            ],
        ];
    }

    public function targetName(array $params): string { return 'Attendance Report'; }
}
