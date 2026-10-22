<?php

/*
Author: Cipher
Created: 2026-10-21 15:46:49 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\EmployeeShift;
use App\Models\Personnel;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AttendanceMetricsService
{
    public function schedule(Personnel $personnel, string $date, string $timezone, ?int $shiftId = null): array
    {
        $assignment = $shiftId || ! $personnel->exists ? null : EmployeeShift::where('personnel_id', $personnel->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->whereHas('shift')->orderByDesc('effective_from')->orderByDesc('id')->first();
        $shift = $shiftId ? Shift::find($shiftId) : $assignment?->shift;
        $start = $shift?->start_time ?: $personnel->shift_start_time;
        $end = $shift?->end_time ?: $personnel->shift_end_time;
        if ((bool) $start !== (bool) $end) {
            throw ValidationException::withMessages(['shift' => 'Configure both shift start and end times.']);
        }
        $scheduledStart = $start ? CarbonImmutable::parse($date.' '.$start, $timezone) : null;
        $scheduledEnd = $end ? CarbonImmutable::parse($date.' '.$end, $timezone) : null;
        if ($scheduledEnd && $scheduledEnd <= $scheduledStart) {
            $scheduledEnd = $scheduledEnd->addDay();
        }
        $grace = array_map('intval', explode(':', $shift?->grace_time ?: '00:00:00'));
        $hours = $shift ? (float) $shift->working_hours : ($scheduledStart ? ($scheduledEnd->getTimestamp() - $scheduledStart->getTimestamp()) / 3600 : null);
        if ($scheduledStart && ($hours <= 0 || $hours > 24)) {
            throw ValidationException::withMessages(['shift' => 'Scheduled working hours must be greater than zero and at most 24.']);
        }

        return [
            'shift_id' => $shift?->id,
            'scheduled_start' => $scheduledStart?->format('Y-m-d H:i:s'),
            'scheduled_end' => $scheduledEnd?->format('Y-m-d H:i:s'),
            'scheduled_hours' => $hours,
            'grace_seconds' => $grace[0] * 3600 + ($grace[1] ?? 0) * 60 + ($grace[2] ?? 0),
            'attendance_timezone' => $timezone,
        ];
    }

    public function calculate(?string $checkIn, ?string $checkOut, array $schedule, string $status = 'present'): array
    {
        $metrics = ['worked_hours' => 0.0, 'overtime_hours' => 0.0, 'late_hours' => 0.0, 'is_late' => false, 'is_early_departure' => false];
        if ($checkOut && ! $checkIn) {
            throw ValidationException::withMessages(['check_out' => 'Check in before checking out.']);
        }
        if (! $checkIn || ! in_array($status, ['present', 'on_duty', 'half_day'], true)) {
            return $metrics;
        }
        $timezone = $schedule['attendance_timezone'];
        $in = CarbonImmutable::parse($checkIn, $timezone);
        $out = $checkOut ? CarbonImmutable::parse($checkOut, $timezone) : null;
        $seconds = $out ? $out->getTimestamp() - $in->getTimestamp() : 0;
        if ($out && ($seconds < 0 || $seconds > 86400)) {
            throw ValidationException::withMessages(['check_out' => 'Check-out must be after check-in and within 24 hours. Ask HR to correct stale attendance.']);
        }
        $metrics['worked_hours'] = round($seconds / 3600, 2);
        if ($schedule['scheduled_start']) {
            $start = CarbonImmutable::parse($schedule['scheduled_start'], $timezone);
            $late = max(0, $in->getTimestamp() - $start->getTimestamp());
            $metrics['is_late'] = $late > ($schedule['grace_seconds'] ?? 0);
            $metrics['late_hours'] = $metrics['is_late'] ? round($late / 3600, 2) : 0.0;
        }
        if ($out && $schedule['scheduled_end']) {
            $metrics['is_early_departure'] = $out < CarbonImmutable::parse($schedule['scheduled_end'], $timezone);
        }
        if ($out && $schedule['scheduled_hours'] !== null) {
            $metrics['overtime_hours'] = round(max(0, $seconds / 3600 - (float) $schedule['scheduled_hours']), 2);
        }

        return $metrics;
    }
}
