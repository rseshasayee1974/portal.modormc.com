<?php

/*
Author: Cipher
Created: 2026-10-21 15:47:58 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\Personnel;
use App\Models\Plant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MobileAttendanceService
{
    public function __construct(private AttendanceMetricsService $metrics) {}

    public function personnel(User $user, int $plantId, bool $lock = false, bool $provision = false): Personnel
    {
        if ($lock) {
            // The user exists even when there is no personnel row to lock yet.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->is_otp_enabled
                && (! $user->lockout_until || $user->lockout_until->isPast()), 403, 'Mobile app access is not available for this account.');
        }
        $query = Personnel::withoutGlobalScope('plant_id')->where('plant_id', $plantId)->where('user_id', $user->id);
        // Stable mapping for legacy users with more than one linked profile.
        $personnel = ($lock ? $query->lockForUpdate() : $query)->orderBy('id')->first();
        if ($personnel) {
            return $personnel;
        }
        $attributes = ['plant_id' => $plantId, 'user_id' => $user->id,
            'first_name' => $user->username ?: 'Mobile user '.$user->id, 'status' => 'active'];
        if (! $provision) {
            // Read endpoints return empty results without creating HR records.
            return new Personnel($attributes);
        }
        // Attendance has a required personnel FK. Reuse that schema, creating only
        // a minimal mapping on the first explicit punch; do not change role grants.
        $plant = Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
        $personnel = Personnel::create($attributes + ['entity_id' => $plant->entity_id]);

        return $personnel;
    }

    public function query(Personnel $personnel)
    {
        return Attendance::withoutGlobalScope('plant_id')->where('personnel_id', $personnel->id)->where('plant_id', $personnel->plant_id)
            ->when(! $personnel->exists, fn ($q) => $q->whereRaw('1 = 0'));
    }

    public function currentSchedule(Personnel $personnel, CarbonImmutable $now): array
    {
        $date = $now->toDateString();
        $previous = $this->metrics->schedule($personnel, $now->subDay()->toDateString(), $now->timezoneName);
        // An after-midnight arrival belongs to the shift that began yesterday.
        if ($previous['scheduled_end'] && substr($previous['scheduled_end'], 0, 10) === $date
            && $now < CarbonImmutable::parse($previous['scheduled_end'], $now->timezoneName)) {
            return ['attendance_date' => $now->subDay()->toDateString()] + $previous;
        }

        return ['attendance_date' => $date] + $this->metrics->schedule($personnel, $date, $now->timezoneName);
    }

    private function ensurePayrollEditable(Personnel $personnel, string $date): void
    {
        $periods = PayrollPeriod::withoutGlobalScope('plant_id')->where('plant_id', $personnel->plant_id)
            ->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->lockForUpdate()->get();
        foreach ($periods as $period) {
            abort_if(in_array($period->status, ['processing', 'completed', 'locked'], true)
                || $period->payslips()->withoutGlobalScope('plant_id')->where('personnel_id', $personnel->id)->exists(),
                409, 'Payroll already covers this attendance date. Ask HR to correct attendance and regenerate payroll.');
        }
    }

    public function checkIn(User $user, int $plantId, ?string $notes): array
    {
        return DB::transaction(function () use ($user, $plantId, $notes) {
            // Serialize punches on an existing employee row, even before attendance exists.
            $personnel = $this->personnel($user, $plantId, true, true);
            $open = Attendance::withoutGlobalScope('plant_id')->where('personnel_id', $personnel->id)
                ->whereNotNull('check_in')->whereNull('check_out')->lockForUpdate()->get();
            abort_if($open->count() > 1, 409, 'Multiple open attendance records require HR correction.');
            if ($open->isNotEmpty()) {
                $entry = $open->first();
                abort_unless((int) $entry->plant_id === $plantId && $entry->source === 'mobile', 409, 'An attendance record is already open. Ask HR to review it.');
                return [$entry, false];
            }
            $now = CarbonImmutable::now(Attendance::TIMEZONE);
            $schedule = $this->currentSchedule($personnel, $now);
            // The existing unique key is employee + date, across plants and soft deletes.
            $entry = Attendance::withoutGlobalScope('plant_id')->withTrashed()->where('personnel_id', $personnel->id)
                ->whereDate('attendance_date', $schedule['attendance_date'])->lockForUpdate()->first();
            if ($entry) {
                abort_unless(! $entry->trashed() && (int) $entry->plant_id === $plantId && $entry->source === 'mobile' && $entry->check_in,
                    409, 'Attendance already exists for this work date. Ask HR to correct it.');
                return [$entry, false];
            }
            $this->ensurePayrollEditable($personnel, $schedule['attendance_date']);
            $checkIn = $now->format('Y-m-d H:i:s');
            $entry = Attendance::create(array_merge([
                'plant_id' => $plantId, 'personnel_id' => $personnel->id,
                'attendance_date' => $schedule['attendance_date'], 'shift_id' => $schedule['shift_id'],
                'check_in' => $checkIn, 'source' => 'mobile', 'status' => 'present', 'notes' => $notes,
            ], $this->metrics->calculate($checkIn, null, $schedule)));

            return [$entry, true];
        }, 3);
    }

    public function checkOut(User $user, int $plantId, int $attendanceId): Attendance
    {
        return DB::transaction(function () use ($user, $plantId, $attendanceId) {
            $personnel = $this->personnel($user, $plantId, true);
            $entry = $this->query($personnel)->lockForUpdate()->findOrFail($attendanceId);
            abort_unless($entry->source === 'mobile' && $entry->check_in, 409, 'This record cannot be checked out through mobile.');
            if ($entry->check_out) {
                return $entry;
            }
            abort_unless(in_array($entry->status, ['present', 'on_duty', 'half_day'], true), 409, 'HR has changed this attendance status.');
            $this->ensurePayrollEditable($personnel, $entry->attendance_date->toDateString());
            $timezone = Attendance::TIMEZONE;
            $schedule = $this->metrics->schedule($personnel, $entry->attendance_date->toDateString(), $timezone, $entry->shift_id);
            $checkOut = CarbonImmutable::now($timezone)->format('Y-m-d H:i:s');
            $entry->fill(array_merge(['check_out' => $checkOut],
                $this->metrics->calculate($entry->getRawOriginal('check_in'), $checkOut, $schedule, $entry->status)))->save();

            return $entry;
        }, 3);
    }

    public function serialize(Attendance $entry): array
    {
        $timezone = Attendance::TIMEZONE;
        $schedule = $this->metrics->schedule($entry->personnel, $entry->attendance_date->toDateString(), $timezone, $entry->shift_id);
        $data = $entry->only(['id', 'plant_id', 'personnel_id', 'shift_id', 'status', 'source', 'notes',
            'worked_hours', 'overtime_hours', 'late_hours', 'is_late', 'is_early_departure']);
        $data['scheduled_hours'] = $schedule['scheduled_hours'] === null ? null : number_format($schedule['scheduled_hours'], 2, '.', '');
        $data['grace_seconds'] = $schedule['grace_seconds'];
        $data['attendance_date'] = $entry->attendance_date->toDateString();
        $data['timezone'] = $timezone;
        foreach (['check_in', 'check_out'] as $field) {
            $raw = $entry->getRawOriginal($field);
            $data[$field] = $raw ? CarbonImmutable::parse($raw, $timezone)->format('Y-m-d H:i:s') : null;
        }
        foreach (['scheduled_start', 'scheduled_end'] as $field) {
            $data[$field] = $schedule[$field] ? CarbonImmutable::parse($schedule[$field], $timezone)->format('Y-m-d H:i:s') : null;
        }
        $data['is_open'] = (bool) ($entry->check_in && ! $entry->check_out);

        return $data;
    }
}
