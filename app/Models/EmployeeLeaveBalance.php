<?php

namespace App\Models;
use App\Traits\TracksModelChanges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
class EmployeeLeaveBalance extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_employee_leave_balances';

    protected $fillable = [
        'personnel_id',
        'leave_type_id',
        'year',
        'opening_balance',
        'accrued',
        'used',
        'balance',
    ];

    protected $casts = [
        'year' => 'integer',
        'opening_balance' => 'decimal:2',
        'accrued' => 'decimal:2',
        'used' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * Recalculate and sync used leave days and net balance for a personnel, leave type, and year.
     */
    public static function syncBalance(int $personnelId, int $leaveTypeId, int $year): self
    {
        $leaveType = LeaveType::find($leaveTypeId);
        $maxDays = $leaveType?->max_days_per_year ?? 0.0;

        $usedDays = (float) LeaveApplication::where('personnel_id', $personnelId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', 'approved')
            ->whereYear('from_date', $year)
            ->sum('days');

        $balanceRecord = static::firstOrCreate(
            [
                'personnel_id' => $personnelId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                'opening_balance' => (float)$maxDays,
                'accrued' => 0.0,
                'used' => 0.0,
                'balance' => (float)$maxDays,
            ]
        );

        $netBalance = max(0.0, ((float)$balanceRecord->opening_balance + (float)$balanceRecord->accrued) - $usedDays);

        $balanceRecord->update([
            'used' => $usedDays,
            'balance' => round($netBalance, 2),
        ]);

        return $balanceRecord;
    }
}
